<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Surestaries\Services\RecalculFranchise;
use Illuminate\Support\Facades\DB;

/**
 * Définit (crée ou met à jour) la franchise d'un type donné pour un conteneur,
 * à partir de date_debut + jours_francs, puis calcule ses montants. Une seule
 * franchise par (conteneur, type).
 */
final class DefinirFranchise
{
    public function __construct(
        private readonly RecalculFranchise $recalcul,
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array{type: string, date_debut: string, jours_francs: int}  $donnees
     */
    public function executer(Conteneur $conteneur, array $donnees): Franchise
    {
        return DB::transaction(function () use ($conteneur, $donnees): Franchise {
            $franchise = Franchise::firstOrNew([
                'conteneur_id' => $conteneur->id,
                'type' => $donnees['type'],
            ]);

            $nouvelle = ! $franchise->exists;

            $franchise->fill([
                'date_debut' => $donnees['date_debut'],
                'jours_francs' => $donnees['jours_francs'],
            ]);

            // conteneur déjà en mémoire : on évite une relecture.
            $franchise->setRelation('conteneur', $conteneur);
            $this->recalcul->recalculer($franchise);
            $franchise->save();

            $nouvelle
                ? $this->auditeur->creation($franchise, 'franchise.definie')
                : $this->auditeur->enregistrer('franchise', $franchise->id, 'franchise.mise_a_jour', null, $franchise->getChanges());

            return $franchise;
        });
    }
}
