<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Adapters;

use App\Domains\Ingestion\Contracts\ExtracteurDocument;
use App\Domains\Ingestion\Data\ChampExtrait;
use App\Domains\Ingestion\Data\DocumentAExtraire;
use App\Domains\Ingestion\Data\ResultatExtraction;
use App\Domains\Ingestion\Data\SchemaExtraction;
use RuntimeException;

/**
 * Extracteur factice : déterministe, sans réseau ni clé. Permet de tester tout
 * le pipeline (dev local et suite de tests) sans fournisseur réel. Configurable
 * par test via simuler()/echouera().
 */
final class ExtracteurFactice implements ExtracteurDocument
{
    /** @var array<string, ChampExtrait>|null */
    private ?array $resultatSimule = null;

    private bool $echouera = false;

    /**
     * @param  array<string, ChampExtrait>  $champs
     */
    public function simuler(array $champs): self
    {
        $this->resultatSimule = $champs;

        return $this;
    }

    public function echouera(bool $echouera = true): self
    {
        $this->echouera = $echouera;

        return $this;
    }

    public function extraire(DocumentAExtraire $document, SchemaExtraction $schema): ResultatExtraction
    {
        if ($this->echouera) {
            throw new RuntimeException('Extraction factice en échec (simulée).');
        }

        return new ResultatExtraction($this->resultatSimule ?? $this->parDefaut($schema), 1);
    }

    /**
     * Un champ neutre par champ du schéma (valeur nulle, confiance élevée).
     *
     * @return array<string, ChampExtrait>
     */
    private function parDefaut(SchemaExtraction $schema): array
    {
        $champs = [];
        foreach (array_keys($schema->champs) as $nom) {
            $champs[$nom] = new ChampExtrait(null, 0.9, 'p.1');
        }

        return $champs;
    }
}
