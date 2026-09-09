<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Data;

use Illuminate\Support\Carbon;

/**
 * Défi OTP émis : la référence et l'échéance (le code n'est jamais exposé ici).
 */
final class DefiOtp
{
    public function __construct(
        public readonly string $invitationId,
        public readonly Carbon $expireAt,
    ) {}
}
