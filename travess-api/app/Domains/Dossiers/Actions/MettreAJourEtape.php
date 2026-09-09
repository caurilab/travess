<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Enums\StatutEtape;
use App\Domains\Dossiers\Models\Etape;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour une étape (statut, SLA, dates, responsable). Passer une étape à
 * « fait » horodate automatiquement la date réelle si elle n'est pas fournie.
 */
final class MettreAJourEtape
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Etape $etape, array $donnees): Etape
    {
        return DB::transaction(function () use ($etape, $donnees): Etape {
            $etape->fill($donnees);

            if ($etape->statut === StatutEtape::Fait && $etape->date_reelle === null) {
                $etape->date_reelle = now();
            }

            $avant = [];
            foreach (array_keys($etape->getDirty()) as $champ) {
                $avant[$champ] = $etape->getOriginal($champ);
            }

            $etape->save();

            if ($avant !== []) {
                $this->auditeur->miseAJour($etape, 'etape.mise_a_jour', $avant);
            }

            return $etape;
        });
    }
}
