<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Policies;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;

/**
 * Autorisations sur les clients (donneurs d'ordre), surface agent. Même matrice
 * que les dossiers : gérant/agent en écriture, comptable en lecture. Le tenant
 * est déjà garanti par le scoping ; revérifié en défense en profondeur.
 */
final class ClientPolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function viewAny(User $acteur): bool
    {
        return in_array($acteur->role, self::LECTURE, true);
    }

    public function view(User $acteur, Client $client): bool
    {
        return $acteur->tenant_id === $client->tenant_id && in_array($acteur->role, self::LECTURE, true);
    }

    public function create(User $acteur): bool
    {
        return in_array($acteur->role, self::ECRITURE, true);
    }

    public function update(User $acteur, Client $client): bool
    {
        return $acteur->tenant_id === $client->tenant_id && in_array($acteur->role, self::ECRITURE, true);
    }
}
