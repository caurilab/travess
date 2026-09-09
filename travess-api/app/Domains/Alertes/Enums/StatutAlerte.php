<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Enums;

/**
 * Cycle de vie d'une alerte.
 */
enum StatutAlerte: string
{
    case Ouverte = 'ouverte';
    case Vue = 'vue';
    case Traitee = 'traitee';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
