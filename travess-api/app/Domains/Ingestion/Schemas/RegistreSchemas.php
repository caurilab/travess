<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Schemas;

use App\Domains\Ingestion\Data\SchemaExtraction;

/**
 * Résout le schéma d'extraction d'un type de document depuis la config
 * (config/ia.php → schemas). Paramétrable sans toucher au code métier.
 */
final class RegistreSchemas
{
    public function pour(string $typeDocument): SchemaExtraction
    {
        /** @var array<string, array<string, string>> $schemas */
        $schemas = config('ia.schemas', []);

        return new SchemaExtraction($typeDocument, $schemas[$typeDocument] ?? []);
    }
}
