<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Contracts;

use App\Domains\Documents\Models\Document;

/**
 * Applique au dossier les données d'une extraction validée par un humain
 * (principe n°5 : l'écriture métier n'a lieu qu'après validation). Un
 * applicateur par type de document ; certains types n'en ont pas (aucune
 * écriture automatique possible).
 */
interface ApplicateurExtraction
{
    /**
     * @param  array<string, mixed>  $donnees  valeurs finales (extraites + corrigées)
     * @return list<string> libellés des enregistrements créés (trace lisible)
     */
    public function appliquer(Document $document, array $donnees): array;
}
