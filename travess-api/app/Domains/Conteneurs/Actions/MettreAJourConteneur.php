<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Models\Conteneur;
use Illuminate\Support\Facades\DB;

/**
 * Met à jour un conteneur (statut, type). Le numéro n'est pas modifiable ici
 * (il identifie le conteneur ; une correction passerait par une re-saisie).
 */
final class MettreAJourConteneur
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Conteneur $conteneur, array $donnees): Conteneur
    {
        return DB::transaction(function () use ($conteneur, $donnees): Conteneur {
            $conteneur->fill($donnees);

            $avant = [];
            foreach (array_keys($conteneur->getDirty()) as $champ) {
                $avant[$champ] = $conteneur->getOriginal($champ);
            }

            $conteneur->save();

            if ($avant !== []) {
                $this->auditeur->miseAJour($conteneur, 'conteneur.mise_a_jour', $avant);
            }

            return $conteneur;
        });
    }
}
