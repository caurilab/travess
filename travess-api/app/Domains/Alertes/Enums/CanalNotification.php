<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Enums;

/**
 * Canal d'envoi d'une notification.
 */
enum CanalNotification: string
{
    case Push = 'push';
    case Whatsapp = 'whatsapp';
    case Email = 'email';
    case Desktop = 'desktop';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
