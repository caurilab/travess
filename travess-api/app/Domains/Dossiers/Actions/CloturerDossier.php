<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Enums\StatutDossier;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clôture manuelle d'un dossier (motif optionnel), auditée. La clôture est
 * possible à tout moment au Lot 1 (la clôture financière viendra au Lot 3) ;
 * seule condition : le dossier ne doit pas être déjà clôturé.
 */
final class CloturerDossier
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    public function executer(Dossier $dossier, ?string $motif = null): Dossier
    {
        if ($dossier->statut === StatutDossier::Cloture) {
            throw ValidationException::withMessages([
                'statut' => [__('Ce dossier est déjà clôturé.')],
            ]);
        }

        return DB::transaction(function () use ($dossier, $motif): Dossier {
            $avant = ['statut' => $dossier->statut->value];

            $dossier->statut = StatutDossier::Cloture;
            $dossier->save();

            $this->auditeur->enregistrer(
                'dossier',
                $dossier->id,
                'dossier.cloture',
                $avant,
                array_filter([
                    'statut' => StatutDossier::Cloture->value,
                    'motif' => $motif,
                ], static fn ($v): bool => $v !== null),
            );

            return $dossier;
        });
    }
}
