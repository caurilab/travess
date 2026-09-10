<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Services;

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Domains\Consommation\Models\ConsommationService;
use Illuminate\Support\Carbon;

/**
 * Journalise la consommation tracking d'un tenant (docs/08 §4), tenant-scopé.
 *
 * Sémantique différente de l'IA : le plafond DUR est GLOBAL (quota mutualisé,
 * cf. GardeQuotaTracking), pas par tenant → ici on COMPTE simplement (chaque
 * appel facturé), pour dimensionner les paliers, facturer le tracking en option
 * et repérer un tenant anormalement gourmand. Un éventuel cap par tenant selon
 * le plan est reporté.
 */
final class DecompteConsommationTracking
{
    /** Compte `$unites` appel(s) pour le mois courant (tenant courant). */
    public function compter(int $unites = 1): void
    {
        $periode = $this->periodeCourante();

        ConsommationService::firstOrCreate(
            ['service' => ServiceConsomme::Tracking->value, 'periode' => $periode],
            ['quantite' => 0],
        );

        ConsommationService::query()
            ->where('service', ServiceConsomme::Tracking->value)
            ->where('periode', $periode)
            ->increment('quantite', $unites);
    }

    /** Libère `$unites` comptées (échec fournisseur). Ne descend jamais sous 0. */
    public function liberer(int $unites = 1): void
    {
        ConsommationService::query()
            ->where('service', ServiceConsomme::Tracking->value)
            ->where('periode', $this->periodeCourante())
            ->where('quantite', '>=', $unites)
            ->decrement('quantite', $unites);
    }

    public function quantiteDuMois(): int
    {
        return (int) ConsommationService::query()
            ->where('service', ServiceConsomme::Tracking->value)
            ->where('periode', $this->periodeCourante())
            ->sum('quantite');
    }

    private function periodeCourante(): string
    {
        return Carbon::now()->format('Y-m');
    }
}
