<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Services;

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Domains\Consommation\Models\ConsommationService;
use App\Domains\Documents\Models\ExtractionIa;
use Illuminate\Support\Carbon;

/**
 * Décompte la consommation IA d'un tenant, tenant-scopé. Le quota est un plafond
 * DUR : l'unité est réservée à l'admission (increment conditionnel atomique) et
 * non comptée après coup — deux lancements concurrents ne peuvent pas dépasser
 * le quota (pas de TOCTOU). En cas d'échec du traitement, l'unité est libérée.
 */
final class DecompteConsommationIa
{
    /**
     * Réserve `unites` pour le mois courant si le plafond n'est pas atteint.
     * L'increment est conditionnel et atomique (une seule requête UPDATE) :
     * renvoie false sans rien consommer si le quota serait dépassé.
     */
    public function reserver(int $quota, int $unites = 1): bool
    {
        $periode = $this->periodeCourante();

        // Garantit la ligne du mois (course de création couverte par l'unique
        // (tenant_id, service, periode)).
        ConsommationService::firstOrCreate(
            ['service' => ServiceConsomme::Ia->value, 'periode' => $periode],
            ['quantite' => 0],
        );

        // Increment conditionnel atomique : n'affecte la ligne que si le total
        // resterait sous le plafond. 0 ligne affectée ⇒ plafond atteint.
        $affectees = ConsommationService::query()
            ->where('service', ServiceConsomme::Ia->value)
            ->where('periode', $periode)
            ->whereRaw('quantite + ? <= ?', [$unites, $quota])
            ->increment('quantite', $unites);

        return $affectees > 0;
    }

    /**
     * Libère `unites` réservées (échec de traitement). Ne descend jamais sous 0.
     */
    public function liberer(int $unites = 1): void
    {
        ConsommationService::query()
            ->where('service', ServiceConsomme::Ia->value)
            ->where('periode', $this->periodeCourante())
            ->where('quantite', '>=', $unites)
            ->decrement('quantite', $unites);
    }

    /**
     * Enregistre le coût réel sur l'extraction. L'unité a déjà été réservée au
     * lancement : on ne réincrémente pas le compteur ici (idempotence au rejeu).
     */
    public function enregistrer(ExtractionIa $extraction, int $unites): void
    {
        $extraction->forceFill(['cout_unite' => $unites])->save();
    }

    public function quantiteDuMois(): int
    {
        return (int) ConsommationService::query()
            ->where('service', ServiceConsomme::Ia->value)
            ->where('periode', $this->periodeCourante())
            ->sum('quantite');
    }

    private function periodeCourante(): string
    {
        return Carbon::now()->format('Y-m');
    }
}
