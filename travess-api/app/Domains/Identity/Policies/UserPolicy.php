<?php

declare(strict_types=1);

namespace App\Domains\Identity\Policies;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les utilisateurs. Garde-fou explicite de l'isolation, User
 * n'étant pas auto-scopé : toute décision vérifie l'appartenance au même tenant.
 */
final class UserPolicy
{
    public function viewAny(User $acteur): bool
    {
        return $acteur->role === RoleUtilisateur::Gerant;
    }

    public function view(User $acteur, User $cible): bool
    {
        if ($acteur->tenant_id !== $cible->tenant_id) {
            return false;
        }

        return $acteur->role === RoleUtilisateur::Gerant || $acteur->id === $cible->id;
    }
}
