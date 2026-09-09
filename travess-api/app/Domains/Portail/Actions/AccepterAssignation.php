<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Enums\StatutDemande;
use App\Domains\Portail\Models\DemandeAssignation;
use App\Domains\Portail\Services\MigrerProprieteDossier;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Acceptation d'une demande d'assignation par le transitaire cible (ADR-013,
 * 7.3b) : déclenche la migration de propriété du dossier, puis clôture la
 * demande (compare-and-set anti-course). S'exécute dans le contexte du
 * transitaire ; le dossier source est lu sous bypass borné.
 *
 * @return array{ancienne_reference: string, nouvelle_reference: string}
 */
final class AccepterAssignation
{
    public function __construct(
        private readonly MigrerProprieteDossier $migration,
        private readonly TenantContext $tenant,
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @return array{ancienne_reference: string, nouvelle_reference: string}
     */
    public function executer(DemandeAssignation $demande): array
    {
        if (! $demande->estDecidable()) {
            throw new HttpException(409, 'Demande non décidable (déjà traitée ou expirée).');
        }

        return DB::transaction(function () use ($demande): array {
            $source = Tenant::findOrFail($demande->tenant_demandeur_id);
            $transitaire = Tenant::findOrFail($demande->transitaire_cible_id);

            // Dossier source (tenant client) lu sous bypass borné ; éligibilité.
            $dossier = $this->tenant->runBypassed(fn (): ?Dossier => Dossier::query()
                ->withoutGlobalScope(TenantScope::class)
                ->whereKey($demande->dossier_id)
                ->first());

            if ($dossier === null || $dossier->posture !== PostureDossier::Autonome || $dossier->tenant_id !== $source->id) {
                throw new HttpException(409, 'Dossier non éligible à la migration.');
            }

            $refs = $this->migration->executer($dossier, $source, $transitaire);

            // Clôture de la demande (compare-and-set : la course perd → 409).
            $affectees = DemandeAssignation::query()
                ->whereKey($demande->getKey())
                ->where('statut', StatutDemande::EnAttente->value)
                ->update([
                    'statut' => StatutDemande::Acceptee->value,
                    'decided_by' => Auth::id(),
                    'decided_at' => now(),
                ]);

            if ($affectees === 0) {
                throw new HttpException(409, 'Demande déjà décidée.');
            }

            $this->auditeur->enregistrer('demande_assignation', (string) $demande->getKey(), 'assignation.acceptee', null, $refs);

            return $refs;
        });
    }
}
