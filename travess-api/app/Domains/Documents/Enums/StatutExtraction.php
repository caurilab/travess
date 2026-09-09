<?php

declare(strict_types=1);

namespace App\Domains\Documents\Enums;

/**
 * État d'une extraction IA d'un document.
 */
enum StatutExtraction: string
{
    case EnFile = 'en_file';
    case Reussi = 'reussi';
    case Echoue = 'echoue';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
