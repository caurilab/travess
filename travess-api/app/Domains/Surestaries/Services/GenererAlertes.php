<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Services;

use App\Domains\Alertes\Enums\StatutAlerte;
use App\Domains\Alertes\Enums\TypeAlerte;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Franchise;
use Illuminate\Support\Carbon;

/**
 * Génère les alertes de surestaries/détention (J-3 / J-1 / J0 avant la fin de
 * franchise) pour le tenant courant. Idempotent : une seule alerte par
 * (conteneur, type) grâce à firstOrCreate + la contrainte d'unicité.
 *
 * S'exécute dans un contexte tenant établi (job JobTenantScoped).
 */
final class GenererAlertes
{
    public function __construct(
        private readonly Auditeur $auditeur,
    ) {}

    public function pourTenantCourant(?Carbon $aujourdhui = null): int
    {
        $aujourdhui = ($aujourdhui ?? Carbon::now())->startOfDay();
        $creees = 0;

        Franchise::query()
            ->where('actif', true)
            ->whereNotNull('date_fin_franchise')
            ->with('conteneur.bl')
            ->get()
            ->each(function (Franchise $franchise) use ($aujourdhui, &$creees): void {
                $type = $this->typeAlerte($franchise, $aujourdhui);
                if ($type === null) {
                    return;
                }

                $alerte = Alerte::firstOrCreate(
                    ['conteneur_id' => $franchise->conteneur_id, 'type' => $type->value],
                    [
                        'dossier_id' => $franchise->conteneur->bl->dossier_id,
                        'montant_menacant' => $franchise->montant_menacant,
                        'statut' => StatutAlerte::Ouverte->value,
                        'canaux_envoyes' => [],
                    ],
                );

                if ($alerte->wasRecentlyCreated) {
                    $creees++;
                    $this->auditeur->creation($alerte, 'alerte.generee');
                }
            });

        return $creees;
    }

    private function typeAlerte(Franchise $franchise, Carbon $aujourdhui): ?TypeAlerte
    {
        $joursRestants = (int) $aujourdhui->diffInDays($franchise->date_fin_franchise->copy()->startOfDay(), false);

        $seuil = match (true) {
            $joursRestants <= 0 => 'j0',
            $joursRestants === 1 => 'j1',
            $joursRestants === 3 => 'j3',
            default => null,
        };

        if ($seuil === null) {
            return null;
        }

        $prefixe = $franchise->type === TypeFranchise::Surestaries ? 'surestaries' : 'detention';

        return TypeAlerte::from("{$prefixe}_{$seuil}");
    }
}
