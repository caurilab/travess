<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Enums;

/**
 * Statut d'activité d'un tenant.
 */
enum StatutTenant: string
{
    case Actif = 'actif';
    case Suspendu = 'suspendu';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
