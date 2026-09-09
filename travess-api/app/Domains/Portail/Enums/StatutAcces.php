<?php

declare(strict_types=1);

namespace App\Domains\Portail\Enums;

/**
 * Cycle de vie d'un accès partagé. Seul « actif » ouvre la lecture (fail-closed).
 */
enum StatutAcces: string
{
    case EnAttente = 'en_attente';
    case Actif = 'actif';
    case Revoque = 'revoque';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
