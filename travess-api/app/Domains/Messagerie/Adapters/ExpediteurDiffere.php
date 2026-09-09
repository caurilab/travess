<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Adapters;

use App\Domains\Messagerie\Contracts\ExpediteurMessage;
use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Data\ResultatEnvoi;
use App\Domains\Messagerie\Enums\StatutEnvoi;

/**
 * Canal réel non encore branché (WhatsApp Business API, SMS/OTP) : on diffère
 * sans réseau (aucune PII journalisée), en attendant les fournisseurs agréés
 * (cf. dette 7.2c). Retourne « en file » sans envoyer réellement.
 */
final class ExpediteurDiffere implements ExpediteurMessage
{
    public function envoyer(Destinataire $destinataire, MessageSortant $message): ResultatEnvoi
    {
        return new ResultatEnvoi(StatutEnvoi::EnFile);
    }
}
