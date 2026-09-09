<?php

declare(strict_types=1);

namespace App\Domains\Paiements\Enums;

/**
 * Opérateur Mobile Money d'un paiement.
 */
enum OperateurPaiement: string
{
    case Orange = 'orange';
    case Mtn = 'mtn';
    case Wave = 'wave';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
