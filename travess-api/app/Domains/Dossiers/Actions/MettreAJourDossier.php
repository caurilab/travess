<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour les champs éditables d'un dossier (statut hors clôture,
 * motif de blocage). La clôture passe par CloturerDossier.
 */
final class MettreAJourDossier
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Dossier $dossier, array $donnees): Dossier
    {
        return DB::transaction(function () use ($dossier, $donnees): Dossier {
            $dossier->fill($donnees);

            $avant = [];
            foreach (array_keys($dossier->getDirty()) as $champ) {
                $avant[$champ] = $dossier->getOriginal($champ);
            }

            $dossier->save();

            if ($avant !== []) {
                $this->auditeur->miseAJour($dossier, 'dossier.mise_a_jour', $avant);
            }

            return $dossier;
        });
    }
}
