<?php

declare(strict_types=1);

namespace App\Domains\Portail\Enums;

/**
 * Cycle de vie d'une demande d'assignation d'un transitaire (ADR-013, 7.3b).
 * Seule « en_attente » (non expirée) est décidable ; l'acceptation déclenche la
 * migration de propriété.
 */
enum StatutDemande: string
{
    case EnAttente = 'en_attente';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';
    case Expiree = 'expiree';
    case Annulee = 'annulee';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
