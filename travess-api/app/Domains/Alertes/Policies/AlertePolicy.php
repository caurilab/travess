<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Policies;

use App\Domains\Alertes\Models\Alerte;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les alertes (tous rôles agence en lecture ; gérant/agent
 * font évoluer le statut ; tenant revérifié).
 */
final class AlertePolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function viewAny(User $acteur): bool
    {
        return in_array($acteur->role, self::LECTURE, true);
    }

    public function update(User $acteur, Alerte $alerte): bool
    {
        return $acteur->tenant_id === $alerte->tenant_id
            && in_array($acteur->role, self::ECRITURE, true);
    }
}
