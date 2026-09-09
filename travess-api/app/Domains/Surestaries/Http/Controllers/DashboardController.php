<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Http\Controllers;

use App\Domains\Alertes\Models\Alerte;
use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Franchise;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Tableaux de bord surestaries (docs/10 §7). Agrégats tenant-scopés (RLS +
 * TenantScope) : « argent en train de brûler » et « surestaries évitées ».
 */
final class DashboardController
{
    /**
     * GET /dashboard/argent-en-feu — conteneurs à risque + montant menaçant cumulé.
     */
    public function argentEnFeu(): JsonResponse
    {
        Gate::authorize('viewAny', Alerte::class);

        $aRisque = Franchise::query()
            ->where('actif', true)
            ->where('montant_menacant', '>', 0)
            ->with('conteneur')
            ->orderByDesc('montant_menacant')
            ->limit(50)
            ->get()
            ->map(fn (Franchise $f): array => [
                'franchise_id' => $f->id,
                'conteneur_id' => $f->conteneur_id,
                'numero' => $f->conteneur->numero,
                'type' => $f->type->value,
                'date_fin_franchise' => $f->date_fin_franchise?->toDateString(),
                'montant_en_cours' => $f->montant_en_cours,
                'montant_menacant' => $f->montant_menacant,
            ])->all();

        return response()->json([
            'data' => [
                'menacant_cumule' => (int) Franchise::where('actif', true)->sum('montant_menacant'),
                'en_cours_cumule' => (int) Franchise::where('actif', true)->sum('montant_en_cours'),
                'conteneurs_a_risque' => $aRisque,
            ],
        ]);
    }

    /**
     * GET /dashboard/surestaries-evitees — métrique de valeur du mois (preuve :
     * un conteneur alerté puis sorti à temps ; évité = menaçant figé − coût réel).
     */
    public function surestariesEvitees(): JsonResponse
    {
        Gate::authorize('viewAny', Alerte::class);

        $debutMois = Carbon::now()->startOfMonth();

        // Menaçant maximal figé par (conteneur, type de franchise) alerté ce mois.
        $menacantParCle = [];
        Alerte::query()
            ->where('created_at', '>=', $debutMois)
            ->whereNotNull('conteneur_id')
            ->get()
            ->each(function (Alerte $alerte) use (&$menacantParCle): void {
                $prefixe = str_starts_with($alerte->type->value, 'surestaries')
                    ? TypeFranchise::Surestaries->value
                    : TypeFranchise::Detention->value;

                if (! str_starts_with($alerte->type->value, 'surestaries')
                    && ! str_starts_with($alerte->type->value, 'detention')) {
                    return; // alertes SLA/blocage : hors métrique surestaries
                }

                $cle = $alerte->conteneur_id.'|'.$prefixe;
                $menacantParCle[$cle] = max($menacantParCle[$cle] ?? 0, (int) $alerte->montant_menacant);
            });

        // Chargement des franchises concernées en une seule passe (pas de N+1).
        $conteneurIds = collect(array_keys($menacantParCle))
            ->map(static fn (string $cle): string => explode('|', $cle)[0])
            ->unique()
            ->all();

        $franchises = Franchise::query()
            ->whereIn('conteneur_id', $conteneurIds)
            ->get()
            ->keyBy(static fn (Franchise $f): string => $f->conteneur_id.'|'.$f->type->value);

        $montantEvite = 0;
        $nombre = 0;

        foreach ($menacantParCle as $cle => $menacant) {
            $franchise = $franchises->get($cle);

            // Conteneur sorti (franchise inactive) : la menace ne s'est pas réalisée.
            if ($franchise !== null && ! $franchise->actif) {
                $evite = max(0, $menacant - $franchise->montant_en_cours);
                if ($evite > 0) {
                    $montantEvite += $evite;
                    $nombre++;
                }
            }
        }

        return response()->json([
            'data' => [
                'mois' => $debutMois->format('Y-m'),
                'montant_evite' => $montantEvite,
                'nombre_conteneurs' => $nombre,
            ],
        ]);
    }
}
