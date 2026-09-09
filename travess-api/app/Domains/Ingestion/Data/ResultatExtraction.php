<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Data;

/**
 * Résultat d'une extraction : les champs proposés + le nombre d'unités
 * consommées (pour le décompte par tenant).
 */
final class ResultatExtraction
{
    /**
     * @param  array<string, ChampExtrait>  $champs
     */
    public function __construct(
        public readonly array $champs,
        public readonly int $unitesConsommees = 1,
    ) {}

    /**
     * Forme persistée dans extractions_ia.champs : {champ: {valeur, confiance, zone_source}}.
     *
     * @return array<string, array{valeur: mixed, confiance: float, zone_source: string|null}>
     */
    public function champsToArray(): array
    {
        return array_map(static fn (ChampExtrait $c): array => $c->toArray(), $this->champs);
    }
}
