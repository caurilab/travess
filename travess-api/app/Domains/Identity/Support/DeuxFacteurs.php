<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Models\User;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Vérification de l'authentification à deux facteurs (TOTP + codes de récupération).
 */
final class DeuxFacteurs
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
    ) {}

    /**
     * Le 2FA est actif pour cet utilisateur (secret présent ET confirmé).
     */
    public function estActif(User $user): bool
    {
        return $user->two_factor_secret !== null
            && $user->two_factor_confirmed_at !== null;
    }

    /**
     * Vérifie un code : d'abord comme code TOTP, puis comme code de récupération
     * (consommé s'il est valide).
     */
    public function verifier(User $user, string $code): bool
    {
        if ($user->two_factor_secret === null) {
            return false;
        }

        // On déchiffre avec l'encrypteur Fortify, celui-là même qui a chiffré.
        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        if ($this->provider->verify($secret, $code)) {
            return true;
        }

        return $this->consommerCodeDeRecuperation($user, $code);
    }

    private function consommerCodeDeRecuperation(User $user, string $code): bool
    {
        if ($user->two_factor_recovery_codes === null) {
            return false;
        }

        /** @var list<string> $codes */
        $codes = json_decode(Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes), true) ?: [];

        if (! in_array($code, $codes, true)) {
            return false;
        }

        $restants = array_values(array_filter($codes, static fn (string $c): bool => $c !== $code));

        $user->forceFill([
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode($restants)),
        ])->save();

        return true;
    }
}
