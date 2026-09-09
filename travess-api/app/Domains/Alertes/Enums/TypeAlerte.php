<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Enums;

/**
 * Type d'alerte métier (paliers surestaries/détention, SLA, blocage).
 */
enum TypeAlerte: string
{
    case SurestariesJ3 = 'surestaries_j3';
    case SurestariesJ1 = 'surestaries_j1';
    case SurestariesJ0 = 'surestaries_j0';
    case DetentionJ3 = 'detention_j3';
    case DetentionJ1 = 'detention_j1';
    case DetentionJ0 = 'detention_j0';
    case SlaDepasse = 'sla_depasse';
    case Blocage = 'blocage';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
