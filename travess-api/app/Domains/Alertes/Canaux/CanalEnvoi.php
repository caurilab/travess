<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Canaux;

use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Identity\Models\User;

/**
 * Adaptateur d'un canal de notification. Isole chaque canal derrière une
 * interface : les lots ultérieurs (push=6, desktop=8, WhatsApp=9) remplacent
 * l'adaptateur sans toucher au moteur d'alertes (principe n°9).
 */
interface CanalEnvoi
{
    public function canal(): CanalNotification;

    public function envoyer(User $destinataire, Alerte $alerte): StatutNotification;
}
