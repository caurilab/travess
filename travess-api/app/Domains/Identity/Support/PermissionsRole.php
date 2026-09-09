<?php

declare(strict_types=1);

namespace App\Domains\Identity\Support;

use App\Domains\Identity\Enums\RoleUtilisateur;

/**
 * Permissions dérivées du rôle (matrice PRD §1.2). Version initiale, à affiner
 * au fil des lots. Exposées par GET /auth/me pour piloter l'UI côté client.
 */
final class PermissionsRole
{
    /**
     * @return list<string>
     */
    public static function pour(RoleUtilisateur $role): array
    {
        return match ($role) {
            RoleUtilisateur::Gerant => [
                'dossiers.gerer', 'finances.gerer', 'users.gerer',
                'tenant.gerer', 'transport.gerer',
            ],
            RoleUtilisateur::Agent => [
                'dossiers.gerer', 'finances.lecture', 'transport.gerer',
            ],
            RoleUtilisateur::Comptable => [
                'finances.gerer', 'dossiers.lecture',
            ],
            RoleUtilisateur::Chauffeur => [
                'missions.assignees',
            ],
            RoleUtilisateur::Client => [
                'portail.consulter', 'portail.payer',
            ],
        };
    }
}
