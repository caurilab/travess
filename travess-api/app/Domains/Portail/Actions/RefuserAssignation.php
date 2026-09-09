<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Portail\Enums\StatutDemande;
use App\Domains\Portail\Models\DemandeAssignation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Refus d'une demande d'assignation par le transitaire cible (ADR-013, 7.3b).
 * Ne migre rien ; le dossier reste autonome chez le client.
 */
final class RefuserAssignation
{
    public function __construct(private readonly Auditeur $auditeur) {}

    public function executer(DemandeAssignation $demande, ?string $motif = null): DemandeAssignation
    {
        if (! $demande->estDecidable()) {
            throw new HttpException(409, 'Demande non décidable (déjà traitée ou expirée).');
        }

        return DB::transaction(function () use ($demande, $motif): DemandeAssignation {
            $affectees = DemandeAssignation::query()
                ->whereKey($demande->getKey())
                ->where('statut', StatutDemande::EnAttente->value)
                ->update([
                    'statut' => StatutDemande::Refusee->value,
                    'motif_refus' => $motif,
                    'decided_by' => Auth::id(),
                    'decided_at' => now(),
                ]);

            if ($affectees === 0) {
                throw new HttpException(409, 'Demande déjà décidée.');
            }

            $this->auditeur->enregistrer('demande_assignation', (string) $demande->getKey(), 'assignation.refusee');

            return $demande->refresh();
        });
    }
}
