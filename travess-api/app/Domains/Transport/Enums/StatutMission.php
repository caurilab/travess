<?php

declare(strict_types=1);

namespace App\Domains\Transport\Enums;

/**
 * État d'une mission de transport terrestre.
 */
enum StatutMission: string
{
    case Prevue = 'prevue';
    case EnRoute = 'en_route';
    case Livree = 'livree';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
