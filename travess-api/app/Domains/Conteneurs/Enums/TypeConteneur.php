<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Enums;

/**
 * Type physique d'un conteneur.
 */
enum TypeConteneur: string
{
    case Vingt = '20';
    case Quarante = '40';
    case QuaranteHc = '40hc';
    case Reefer = 'reefer';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
