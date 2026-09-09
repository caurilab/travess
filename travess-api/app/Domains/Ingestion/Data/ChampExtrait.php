<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Data;

/**
 * Un champ extrait par l'IA : sa valeur, un niveau de confiance (0..1) et la
 * zone source telle que déclarée par le modèle (indicative — l'humain valide).
 */
final class ChampExtrait
{
    public function __construct(
        public readonly mixed $valeur,
        public readonly float $confiance,
        public readonly ?string $zoneSource = null,
    ) {}

    /**
     * @return array{valeur: mixed, confiance: float, zone_source: string|null}
     */
    public function toArray(): array
    {
        return [
            'valeur' => $this->valeur,
            'confiance' => $this->confiance,
            'zone_source' => $this->zoneSource,
        ];
    }
}
