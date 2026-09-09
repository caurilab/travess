<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Policies;

use App\Domains\Dossiers\Models\Etape;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les étapes : gérant et agent peuvent éditer (dans leur
 * tenant) ; le tenant est déjà garanti par le scoping, revérifié ici.
 */
final class EtapePolicy
{
    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function update(User $acteur, Etape $etape): bool
    {
        return $acteur->tenant_id === $etape->tenant_id
            && in_array($acteur->role, self::ECRITURE, true);
    }
}
