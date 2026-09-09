<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Surestaries\Services\RecalculFranchise;
use Illuminate\Support\Facades\DB;

/**
 * Ajuste une franchise (date_debut / jours_francs) et recalcule immédiatement
 * ses montants dérivés. Audité.
 */
final class AjusterFranchise
{
    public function __construct(
        private readonly RecalculFranchise $recalcul,
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Franchise $franchise, array $donnees): Franchise
    {
        return DB::transaction(function () use ($franchise, $donnees): Franchise {
            $franchise->fill($donnees);

            $avant = [];
            foreach (array_keys($franchise->getDirty()) as $champ) {
                $avant[$champ] = $franchise->getOriginal($champ);
            }

            $this->recalcul->recalculer($franchise);
            $franchise->save();

            $this->auditeur->miseAJour($franchise, 'franchise.ajustee', $avant);

            return $franchise;
        });
    }
}
