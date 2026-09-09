<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Actions;

use App\Domains\Alertes\Models\Alerte;
use App\Domains\Audit\Services\Auditeur;
use Illuminate\Support\Facades\DB;

/**
 * Fait évoluer le statut d'une alerte (ouverte → vue → traitée). Audité.
 */
final class MettreAJourAlerte
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function executer(Alerte $alerte, array $donnees): Alerte
    {
        return DB::transaction(function () use ($alerte, $donnees): Alerte {
            $avant = ['statut' => $alerte->statut->value];

            $alerte->fill($donnees);
            $alerte->save();

            if ($alerte->wasChanged()) {
                $this->auditeur->miseAJour($alerte, 'alerte.mise_a_jour', $avant);
            }

            return $alerte;
        });
    }
}
