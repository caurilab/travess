<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Adapters;

use App\Domains\Messagerie\Contracts\ExpediteurMessage;
use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Data\ResultatEnvoi;
use App\Domains\Messagerie\Enums\StatutEnvoi;
use App\Domains\Messagerie\Mail\CorrespondanceMail;
use App\Domains\Messagerie\Mail\InvitationMail;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * Expéditeur e-mail RÉEL (7.2b), isolé derrière ExpediteurMessage (principe n°9).
 * Envoi SYNCHRONE : appelé depuis le job EnvoyerInvitation (déjà async), on ne
 * re-met pas la mailable en file (le lien secret ne transite pas par la file).
 */
final class ExpediteurEmail implements ExpediteurMessage
{
    public function envoyer(Destinataire $destinataire, MessageSortant $message): ResultatEnvoi
    {
        $mailable = match ($message->gabarit) {
            'invitation_portail' => new InvitationMail(
                $message->params['lien'] ?? '',
                $message->params['reference'] ?? '',
            ),
            'correspondance_libre' => new CorrespondanceMail(
                $message->params['objet'] ?? '',
                $message->params['corps'] ?? '',
            ),
            default => throw new InvalidArgumentException("Gabarit e-mail inconnu : {$message->gabarit}"),
        };

        Mail::to($destinataire->adresse)->send($mailable);

        return new ResultatEnvoi(StatutEnvoi::Accepte);
    }
}
