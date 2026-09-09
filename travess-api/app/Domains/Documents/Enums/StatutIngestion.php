<?php

declare(strict_types=1);

namespace App\Domains\Documents\Enums;

/**
 * État du pipeline d'ingestion documentaire (IA).
 */
enum StatutIngestion: string
{
    case None = 'none';
    case EnFile = 'en_file';
    case Extrait = 'extrait';
    case Valide = 'valide';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
