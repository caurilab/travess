<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Enums;

/**
 * Issue d'un envoi de message par un expéditeur.
 */
enum StatutEnvoi: string
{
    case Accepte = 'accepte';
    case EnFile = 'en_file';
    case Echec = 'echec';
}
