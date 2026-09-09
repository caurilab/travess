<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Enums;

/**
 * Canal de transport d'un message sortant. Distinct des canaux de notification
 * des Alertes (couplés à un compte) : ici le destinataire est une PII brute.
 */
enum CanalMessage: string
{
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Sms = 'sms';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
