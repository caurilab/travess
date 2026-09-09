<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Enums;

/**
 * Statut de traitement d'un dossier.
 */
enum StatutDossier: string
{
    case Ouvert = 'ouvert';
    case EnCours = 'en_cours';
    case Bloque = 'bloque';
    case Cloture = 'cloture';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
