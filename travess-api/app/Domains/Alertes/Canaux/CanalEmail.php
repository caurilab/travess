<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Canaux;

use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Domains\Alertes\Mail\AlerteMail;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Canal e-mail (réel) : met l'e-mail en file (principe n°4).
 */
final class CanalEmail implements CanalEnvoi
{
    public function canal(): CanalNotification
    {
        return CanalNotification::Email;
    }

    public function envoyer(User $destinataire, Alerte $alerte): StatutNotification
    {
        Mail::to($destinataire->email)->queue(new AlerteMail($alerte));

        return StatutNotification::Envoye;
    }
}
