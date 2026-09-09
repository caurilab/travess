<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Enums;

/**
 * Source d'un snapshot de suivi conteneur.
 */
enum SourceSuiviTracking: string
{
    case Jsoncargo = 'jsoncargo';
    case Imap = 'imap';
    case Manuel = 'manuel';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
