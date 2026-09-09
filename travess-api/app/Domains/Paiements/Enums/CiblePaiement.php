<?php

declare(strict_types=1);

namespace App\Domains\Paiements\Enums;

/**
 * Objet financier visé par un paiement portail.
 */
enum CiblePaiement: string
{
    case Charge = 'charge';
    case Honoraire = 'honoraire';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
