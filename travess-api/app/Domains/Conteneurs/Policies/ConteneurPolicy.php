<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Policies;

use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les conteneurs (gérant/agent en écriture, tous rôles
 * agence en lecture ; tenant revérifié).
 */
final class ConteneurPolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function create(User $acteur): bool
    {
        return in_array($acteur->role, self::ECRITURE, true);
    }

    public function view(User $acteur, Conteneur $conteneur): bool
    {
        return $acteur->tenant_id === $conteneur->tenant_id
            && in_array($acteur->role, self::LECTURE, true);
    }

    public function update(User $acteur, Conteneur $conteneur): bool
    {
        return $acteur->tenant_id === $conteneur->tenant_id
            && in_array($acteur->role, self::ECRITURE, true);
    }
}
