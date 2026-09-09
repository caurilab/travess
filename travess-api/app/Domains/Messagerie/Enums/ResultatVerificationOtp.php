<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Enums;

/**
 * Issue de la vérification d'un code OTP.
 */
enum ResultatVerificationOtp: string
{
    case Valide = 'valide';
    case Invalide = 'invalide';
    case Expire = 'expire';
    case Verrouille = 'verrouille';
}
