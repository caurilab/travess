<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Data;

/**
 * Compteur global du quota fournisseur (endpoint stats). Sert le plafond de
 * sécurité (docs/08 §3) : au-delà d'un pourcentage, on coupe le polling auto.
 * Quota MUTUALISÉ (global), distinct de la consommation par tenant.
 */
final class StatsQuotaData
{
    public function __construct(
        public readonly string $plan,
        public readonly int $requetesTotal,
        public readonly int $requetesFaites,
        public readonly int $requetesDisponibles,
    ) {}

    /** Fraction du quota mensuel consommée (0.0 → 1.0). */
    public function pourcentageConsomme(): float
    {
        if ($this->requetesTotal <= 0) {
            return 0.0;
        }

        return min(1.0, $this->requetesFaites / $this->requetesTotal);
    }
}
