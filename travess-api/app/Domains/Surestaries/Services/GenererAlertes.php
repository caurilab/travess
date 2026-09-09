<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Services;

use App\Domains\Alertes\Enums\StatutAlerte;
use App\Domains\Alertes\Enums\TypeAlerte;
use App\Domains\Alertes\Jobs\EnvoyerAlerte;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Audit\Services\Auditeur;
use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Franchise;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        private readonly TenantContext $tenant,
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

                // Création + audit dans une même transaction (invariant ADR-007).
                $alerte = DB::transaction(function () use ($franchise, $type, &$creees): Alerte {
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

                    return $alerte;
                });

                // Envoi en file (principe n°4), y compris pour une alerte ouverte
                // jamais dispatchée (ex. envoi précédent échoué) — EnvoyerAlerte
                // est idempotent.
                if ($alerte->statut === StatutAlerte::Ouverte && $alerte->canaux_envoyes === []) {
                    EnvoyerAlerte::dispatch($this->tenant->idOrFail(), $alerte->id);
                }
            });

        return $creees;
    }

    private function typeAlerte(Franchise $franchise, Carbon $aujourdhui): ?TypeAlerte
    {
        $joursRestants = (int) $aujourdhui->diffInDays($franchise->date_fin_franchise->copy()->startOfDay(), false);

        // Seuils en « <= » (et non égalité stricte) : si un run quotidien est
        // manqué, l'alerte du palier est tout de même créée à la prochaine
        // occasion (l'unicité (conteneur, type) empêche les doublons).
        $seuil = match (true) {
            $joursRestants <= 0 => 'j0',
            $joursRestants <= 1 => 'j1',
            $joursRestants <= 3 => 'j3',
            default => null,
        };

        if ($seuil === null) {
            return null;
        }

        $prefixe = $franchise->type === TypeFranchise::Surestaries ? 'surestaries' : 'detention';

        return TypeAlerte::from("{$prefixe}_{$seuil}");
    }
}
