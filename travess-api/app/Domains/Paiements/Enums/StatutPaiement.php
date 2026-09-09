<?php

declare(strict_types=1);

namespace App\Domains\Paiements\Enums;

/**
 * Cycle de vie d'un paiement Mobile Money.
 */
enum StatutPaiement: string
{
    case Initie = 'initie';
    case EnAttente = 'en_attente';
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
