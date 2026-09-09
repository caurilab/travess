<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Policies;

use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les connaissements (gérant/agent en écriture).
 */
final class BlPolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function create(User $acteur): bool
    {
        return in_array($acteur->role, self::ECRITURE, true);
    }

    public function view(User $acteur, Bl $bl): bool
    {
        return $acteur->tenant_id === $bl->tenant_id
            && in_array($acteur->role, self::LECTURE, true);
    }
}
