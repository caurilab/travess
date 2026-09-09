<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Enums;

/**
 * Plan d'abonnement d'un tenant (société de transit).
 */
enum PlanTenant: string
{
    case Essentiel = 'essentiel';
    case Pro = 'pro';
    case Business = 'business';
    case SurMesure = 'sur_mesure';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
