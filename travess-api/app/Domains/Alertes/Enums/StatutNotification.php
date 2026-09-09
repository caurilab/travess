<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Enums;

/**
 * Statut d'envoi d'une notification (journal des envois).
 */
enum StatutNotification: string
{
    case EnAttente = 'en_attente';
    case Envoye = 'envoye';
    case Echoue = 'echoue';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
