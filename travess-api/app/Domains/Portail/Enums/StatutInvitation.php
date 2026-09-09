<?php

declare(strict_types=1);

namespace App\Domains\Portail\Enums;

/**
 * Cycle de vie d'une invitation portail (ADR-013). Seule « emise » (non expirée)
 * est réclamable ; la consommation est à usage unique.
 */
enum StatutInvitation: string
{
    case Emise = 'emise';
    case Consommee = 'consommee';
    case Expiree = 'expiree';
    case Revoquee = 'revoquee';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
