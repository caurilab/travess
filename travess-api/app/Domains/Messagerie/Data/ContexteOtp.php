<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Data;

/**
 * Contexte liant un défi OTP à l'invitation qui le motive : un OTP émis pour une
 * invitation ne peut jamais valider une autre (base du rate-limit par invitation).
 */
final class ContexteOtp
{
    public function __construct(
        public readonly string $invitationId,
    ) {}
}
