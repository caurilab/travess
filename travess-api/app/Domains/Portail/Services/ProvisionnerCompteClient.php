<?php

declare(strict_types=1);

namespace App\Domains\Portail\Services;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Enums\TypeTenant;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provisionne le compte client du portail (ADR-013, 7.2) : un workspace tenant
 * type=client (sans quota) + un User rôle client identifié par téléphone.
 *
 * Opération système qui PRÉCÈDE le tenant du client → runBypassed borné (créer
 * le tenant + le user, rien de plus). Non-cumul strict : un numéro déjà associé
 * à un compte non-client est refusé ; un compte client existant est réutilisé.
 */
final class ProvisionnerCompteClient
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function executer(string $telephone, string $nom, ?string $motDePasse = null): User
    {
        return $this->tenant->runBypassed(function () use ($telephone, $nom, $motDePasse): User {
            $existant = User::query()->where('telephone', $telephone)->first();

            if ($existant !== null) {
                if ($existant->role !== RoleUtilisateur::Client) {
                    throw new HttpException(409, 'Ce numéro est déjà associé à un compte non-client.');
                }

                // Même personne, compte client déjà provisionné : on le réutilise
                // (l'accès au nouveau dossier est ajouté par l'appelant).
                return $existant;
            }

            $workspace = Tenant::create([
                'nom' => $nom,
                'type' => TypeTenant::Client->value,
                'quota_ia_mensuel' => 0,
                'quota_tracking_mensuel' => 0,
            ]);

            $user = new User([
                'nom' => $nom,
                'telephone' => $telephone,
                'role' => RoleUtilisateur::Client->value,
                'password' => $motDePasse ?? Str::random(48),
            ]);
            $user->forceFill(['tenant_id' => $workspace->id])->save();

            return $user;
        });
    }
}
