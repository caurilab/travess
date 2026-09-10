<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Enums;

/**
 * Sens d'un message de correspondance armateur.
 *
 * `sortant` : émis par le transitaire (demande, relance). `entrant` : reçu de
 * l'armateur (ingestion IMAP, reportée) — présent dès maintenant pour figer le
 * schéma sans migration ultérieure.
 */
enum DirectionMessage: string
{
    case Sortant = 'sortant';
    case Entrant = 'entrant';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
