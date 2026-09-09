<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Contracts;

use App\Domains\Messagerie\Data\ContexteOtp;
use App\Domains\Messagerie\Data\DefiOtp;
use App\Domains\Messagerie\Enums\ResultatVerificationOtp;

/**
 * Service OTP (principe n°9). Deux familles de fournisseurs derrière la même
 * interface : auto-géré (on génère/stocke/vérifie le code) ou délégué (Twilio
 * Verify, OTP WhatsApp natif). Le code n'apparaît jamais dans le contrat.
 */
interface ServiceOtp
{
    public function emettre(string $telephone, ContexteOtp $contexte): DefiOtp;

    public function verifier(string $telephone, string $code, ContexteOtp $contexte): ResultatVerificationOtp;
}
