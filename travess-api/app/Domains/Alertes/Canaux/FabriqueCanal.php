<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Canaux;

use App\Domains\Alertes\Enums\CanalNotification;

/**
 * Résout l'adaptateur d'un canal. Email est réel ; les autres sont différés
 * jusqu'à leur lot (push=6, desktop=8, WhatsApp=9).
 */
final class FabriqueCanal
{
    public function pour(CanalNotification $canal): CanalEnvoi
    {
        return match ($canal) {
            CanalNotification::Email => new CanalEmail,
            default => new CanalDiffere($canal),
        };
    }
}
