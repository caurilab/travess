<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Contracts;

use App\Domains\Messagerie\Data\Destinataire;
use App\Domains\Messagerie\Data\MessageSortant;
use App\Domains\Messagerie\Data\ResultatEnvoi;

/**
 * Expéditeur d'un message vers une PII brute, canal-agnostique (principe n°9).
 * Le fournisseur réel (WhatsApp Business API, SMS, email) est isolé derrière
 * cette interface ; un expéditeur factice permet de tout tester sans réseau.
 */
interface ExpediteurMessage
{
    public function envoyer(Destinataire $destinataire, MessageSortant $message): ResultatEnvoi;
}
