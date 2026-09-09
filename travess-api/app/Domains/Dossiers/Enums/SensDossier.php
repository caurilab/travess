<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Enums;

/**
 * Sens logistique d'un dossier de transit.
 */
enum SensDossier: string
{
    case Import = 'import';
    case Export = 'export';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
