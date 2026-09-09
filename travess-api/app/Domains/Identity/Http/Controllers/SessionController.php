<?php

declare(strict_types=1);

namespace App\Domains\Identity\Http\Controllers;

use App\Domains\Identity\Http\Requests\LoginRequest;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\DeuxFacteurs;
use App\Domains\Identity\Support\PermissionsRole;
use App\Domains\Tenancy\Http\Resources\TenantResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Session par jeton (Sanctum). Contrat docs/10 §2.
 */
final class SessionController
{
    /**
     * Hash bcrypt leurre : vérifié quand l'email est inconnu pour égaliser le
     * temps de réponse et empêcher l'énumération d'emails par timing (M-1).
     */
    private const HASH_LEURRE = '$2y$12$ueDbRHNhmNzcsJO57DXn5ew9X4vg.i5XJZcJjnDGhkFUA3xvNvlo2';

    public function __construct(
        private readonly DeuxFacteurs $deuxFacteurs,
    ) {}

    /**
     * POST /auth/login — renvoie un jeton, ou un défi 2FA si activé.
     */
    public function login(LoginRequest $requete): JsonResponse
    {
        $donnees = $requete->validated();

        $email = Str::lower($donnees['email']); // lookup normalisé (F-5)
        $user = User::where('email', $email)->first();

        // Message générique + temps de calcul égalisé (anti-énumération, M-1) :
        // on exécute toujours un Hash::check, même quand l'email est inconnu.
        if ($user === null) {
            Hash::check($donnees['password'], self::HASH_LEURRE);

            throw ValidationException::withMessages([
                'email' => [__('Identifiants invalides.')],
            ]);
        }

        if (! Hash::check($donnees['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('Identifiants invalides.')],
            ]);
        }

        if ($this->deuxFacteurs->estActif($user)) {
            $code = $donnees['code'] ?? null;

            if ($code === null || $code === '') {
                return response()->json(['data' => ['challenge' => '2fa']]);
            }

            if (! $this->deuxFacteurs->verifier($user, $code)) {
                throw ValidationException::withMessages([
                    'code' => [__('Code d\'authentification invalide.')],
                ]);
            }
        }

        return response()->json([
            'data' => ['token' => $user->createToken('api')->plainTextToken],
        ]);
    }

    /**
     * GET /auth/me — identité, tenant et permissions résolues.
     */
    public function me(Request $requete): JsonResponse
    {
        /** @var User $user */
        $user = $requete->user();

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'tenant' => new TenantResource($user->tenant),
                'permissions' => PermissionsRole::pour($user->role),
            ],
        ]);
    }

    /**
     * POST /auth/logout — révoque le jeton courant.
     */
    public function logout(Request $requete): JsonResponse
    {
        $requete->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    /**
     * POST /auth/refresh — rotation du jeton courant.
     */
    public function refresh(Request $requete): JsonResponse
    {
        /** @var User $user */
        $user = $requete->user();
        $user->currentAccessToken()->delete();

        return response()->json([
            'data' => ['token' => $user->createToken('api')->plainTextToken],
        ]);
    }
}
