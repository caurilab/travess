<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Applicateurs;

use App\Domains\Documents\Enums\TypeDocument;
use App\Domains\Ingestion\Contracts\ApplicateurExtraction;
use Illuminate\Contracts\Container\Container;

/**
 * Choisit l'applicateur adapté au type de document. Renvoie null pour les types
 * sans écriture métier automatisable (l'extraction reste consultable, mais rien
 * n'est appliqué au dossier).
 */
final class FabriqueApplicateur
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function pour(TypeDocument $type): ?ApplicateurExtraction
    {
        return match ($type) {
            TypeDocument::Bl => $this->container->make(ApplicateurBl::class),
            default => null,
        };
    }
}
