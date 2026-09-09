<?php

declare(strict_types=1);

namespace App\Domains\Identity\Enums;

/**
 * Rôle d'un utilisateur au sein d'un tenant (matrice des rôles, PRD §1.2).
 *
 * Le rôle « client » est rattaché à un donneur d'ordre et n'accède qu'au
 * portail. Le super-admin éditeur (hors tenant) n'est pas modélisé ici.
 */
enum RoleUtilisateur: string
{
    case Gerant = 'gerant';
    case Agent = 'agent';
    case Comptable = 'comptable';
    case Chauffeur = 'chauffeur';
    case Client = 'client';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
