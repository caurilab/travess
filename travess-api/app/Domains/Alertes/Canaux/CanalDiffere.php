<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Canaux;

use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Identity\Models\User;

/**
 * Canal non encore disponible (push=Lot 6, desktop=Lot 8, WhatsApp=Lot 9).
 * On journalise l'intention (statut « en_attente ») sans envoyer : le pipeline
 * complet est exercé dès maintenant ; les lots ultérieurs remplaceront cet
 * adaptateur par l'implémentation réelle.
 */
final class CanalDiffere implements CanalEnvoi
{
    public function __construct(
        private readonly CanalNotification $canal,
    ) {}

    public function canal(): CanalNotification
    {
        return $this->canal;
    }

    public function envoyer(User $destinataire, Alerte $alerte): StatutNotification
    {
        return StatutNotification::EnAttente;
    }
}
