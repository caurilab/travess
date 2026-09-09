<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Policies;

use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les franchises (gérant/agent en écriture, tous rôles agence
 * en lecture ; tenant revérifié).
 */
final class FranchisePolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function view(User $acteur, Franchise $franchise): bool
    {
        return $acteur->tenant_id === $franchise->tenant_id
            && in_array($acteur->role, self::LECTURE, true);
    }

    public function update(User $acteur, Franchise $franchise): bool
    {
        return $acteur->tenant_id === $franchise->tenant_id
            && in_array($acteur->role, self::ECRITURE, true);
    }
}
