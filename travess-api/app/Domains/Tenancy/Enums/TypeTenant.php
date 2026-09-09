<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Enums;

/**
 * Type de tenant (ADR-013). Un transitaire est une agence ; un client est un
 * workspace léger (donneur d'ordre qui se connecte au portail), sans quota IA
 * ni tracking. Le type ne se cumule pas dans un même compte.
 */
enum TypeTenant: string
{
    case Transitaire = 'transitaire';
    case Client = 'client';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
