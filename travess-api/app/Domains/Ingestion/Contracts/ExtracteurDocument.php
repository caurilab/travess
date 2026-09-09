<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Contracts;

use App\Domains\Ingestion\Data\DocumentAExtraire;
use App\Domains\Ingestion\Data\ResultatExtraction;
use App\Domains\Ingestion\Data\SchemaExtraction;

/**
 * Contrat d'extraction documentaire. Le domaine ne connaît QUE cette interface ;
 * le fournisseur concret (laravel/ai → Claude, ou un extracteur factice) est
 * isolé derrière un adaptateur (principe n°9) et changeable par configuration
 * sans réécrire le domaine.
 */
interface ExtracteurDocument
{
    public function extraire(DocumentAExtraire $document, SchemaExtraction $schema): ResultatExtraction;
}
