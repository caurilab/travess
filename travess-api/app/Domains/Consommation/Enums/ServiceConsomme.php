<?php

declare(strict_types=1);

namespace App\Domains\Consommation\Enums;

/**
 * Service mesuré pour la facturation à l'usage (paliers, dépassement).
 */
enum ServiceConsomme: string
{
    case Tracking = 'tracking';
    case Ia = 'ia';
    case Whatsapp = 'whatsapp';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
