<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Enums\StatutDemande;
use App\Domains\Portail\Models\DemandeAssignation;
use App\Domains\Tenancy\Enums\TypeTenant;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Un client autonome demande à un transitaire de reprendre son dossier
 * (ADR-013, 7.3b). Idempotent (une seule demande pendante par dossier). La RLS
 * d'insertion garantit que le demandeur possède réellement le dossier ET qu'il
 * est encore autonome. L'annuaire opt-in du transitaire relève du sous-lot 7.4.
 */
final class DemanderAssignation
{
    public function __construct(
        private readonly Auditeur $auditeur,
        private readonly TenantContext $tenant,
    ) {}

    public function executer(Dossier $dossier, string $transitaireId, ?string $message = null): DemandeAssignation
    {
        $cible = Tenant::find($transitaireId);

        // Seul un transitaire inscrit à l'annuaire (opt-in, 7.4) est assignable.
        if ($cible === null || $cible->type !== TypeTenant::Transitaire || ! $cible->annuaire_public) {
            throw new HttpException(422, 'Transitaire cible invalide ou non disponible.');
        }

        return DB::transaction(function () use ($dossier, $cible, $message): DemandeAssignation {
            $pendante = DemandeAssignation::query()
                ->where('dossier_id', $dossier->id)
                ->where('statut', StatutDemande::EnAttente->value)
                ->first();

            if ($pendante !== null) {
                return $pendante;
            }

            $demande = DemandeAssignation::create([
                'dossier_id' => $dossier->id,
                'tenant_demandeur_id' => $this->tenant->idOrFail(),
                'transitaire_cible_id' => $cible->id,
                'statut' => StatutDemande::EnAttente->value,
                'message' => $message,
                'created_by' => Auth::id(),
                'expire_at' => now()->addDays(7),
            ]);

            $this->auditeur->enregistrer('demande_assignation', (string) $demande->getKey(), 'assignation.demandee');

            return $demande;
        });
    }
}
