<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\DeuxFacteurs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

/**
 * Gestion du 2FA TOTP de l'utilisateur courant. Contrat docs/10 §2 (2FA).
 */
final class DeuxFacteursController
{
    /**
     * POST /auth/2fa/activer — génère un secret (non confirmé) et les codes de
     * récupération, et renvoie de quoi configurer une application TOTP.
     *
     * Le secret n'est exposé que pendant l'enrôlement (2FA non confirmé). Sur un
     * 2FA déjà actif, on refuse : un jeton volé ne doit pas pouvoir récupérer le
     * secret permanent ni les codes de récupération (E-1).
     */
    public function activer(Request $requete, EnableTwoFactorAuthentication $activer): JsonResponse
    {
        /** @var User $user */
        $user = $requete->user();

        if ($user->two_factor_confirmed_at !== null) {
            abort(409, 'Le 2FA est déjà actif. Désactivez-le avant de le reconfigurer.');
        }

        $activer($user);
        $user->refresh();

        $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

        return response()->json([
            'data' => [
                'secret' => $secret,
                'otpauth_url' => $this->otpauthUrl($user->email, $secret),
                'codes_recuperation' => $this->codesRecuperation($user),
            ],
        ]);
    }

    /**
     * POST /auth/2fa/confirmer — confirme l'activation avec un code TOTP.
     */
    public function confirmer(Request $requete, ConfirmTwoFactorAuthentication $confirmer): JsonResponse
    {
        $requete->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = $requete->user();

        // Lève une ValidationException (422) si le code est invalide.
        $confirmer($user, $requete->input('code'));

        return response()->json(status: 204);
    }

    /**
     * POST /auth/2fa/desactiver — désactive le 2FA après vérification d'un
     * facteur frais (TOTP ou code de récupération). Un simple jeton ne suffit
     * pas à retirer le second facteur (M-3).
     */
    public function desactiver(
        Request $requete,
        DeuxFacteurs $deuxFacteurs,
        DisableTwoFactorAuthentication $desactiver,
    ): JsonResponse {
        $requete->validate(['code' => ['required', 'string']]);

        /** @var User $user */
        $user = $requete->user();

        if (! $deuxFacteurs->estActif($user) || ! $deuxFacteurs->verifier($user, $requete->input('code'))) {
            throw ValidationException::withMessages([
                'code' => [__('Code d\'authentification invalide.')],
            ]);
        }

        $desactiver($user);

        return response()->json(status: 204);
    }

    /**
     * @return list<string>
     */
    private function codesRecuperation(User $user): array
    {
        if ($user->two_factor_recovery_codes === null) {
            return [];
        }

        return json_decode(Fortify::currentEncrypter()->decrypt($user->two_factor_recovery_codes), true) ?: [];
    }

    private function otpauthUrl(string $email, string $secret): string
    {
        $issuer = rawurlencode((string) config('app.name', 'Travess'));
        $compte = rawurlencode($email);

        return "otpauth://totp/{$issuer}:{$compte}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
    }
}
