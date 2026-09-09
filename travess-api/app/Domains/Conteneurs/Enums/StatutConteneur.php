<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Enums;

/**
 * Statut logistique d'un conteneur.
 */
enum StatutConteneur: string
{
    case ATraiter = 'a_traiter';
    case Enleve = 'enleve';
    case Livre = 'livre';
    case Rendu = 'rendu';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
