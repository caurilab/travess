<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Data;

/**
 * Descripteur neutre d'un document à extraire (aucune dépendance provider).
 */
final class DocumentAExtraire
{
    public function __construct(
        public readonly string $disque,
        public readonly string $chemin,
        public readonly ?string $mime,
        public readonly string $typeDocument,
    ) {}
}
