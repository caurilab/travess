<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Data;

/**
 * Schéma d'extraction d'un type de document : les champs attendus et leur type.
 * Neutre vis-à-vis du provider ; l'adaptateur le traduit en sortie structurée.
 */
final class SchemaExtraction
{
    /**
     * @param  array<string, string>  $champs  nom du champ => type (string, number, date, liste…)
     */
    public function __construct(
        public readonly string $typeDocument,
        public readonly array $champs,
    ) {}
}
