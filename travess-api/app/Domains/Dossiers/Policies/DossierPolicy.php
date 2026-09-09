<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Policies;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les dossiers (surface agent). Matrice PRD §1.2 :
 * gérant (RW), agent (RW), comptable (lecture). Client et chauffeur n'accèdent
 * pas à la surface agent. Le tenant est déjà garanti par le scoping ; on le
 * revérifie en défense en profondeur.
 *
 * Note : le raffinement « l'agent ne voit que ses dossiers assignés » est
 * reporté ; au Lot 1 l'isolation dure est le tenant.
 */
final class DossierPolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function viewAny(User $acteur): bool
    {
        return in_array($acteur->role, self::LECTURE, true);
    }

    public function view(User $acteur, Dossier $dossier): bool
    {
        return $this->memeTenant($acteur, $dossier) && in_array($acteur->role, self::LECTURE, true);
    }

    public function create(User $acteur): bool
    {
        return in_array($acteur->role, self::ECRITURE, true);
    }

    public function update(User $acteur, Dossier $dossier): bool
    {
        return $this->memeTenant($acteur, $dossier) && in_array($acteur->role, self::ECRITURE, true);
    }

    public function cloturer(User $acteur, Dossier $dossier): bool
    {
        return $this->update($acteur, $dossier);
    }

    public function assigner(User $acteur, Dossier $dossier): bool
    {
        // L'assignation des agents est une action de gestion (gérant).
        return $this->memeTenant($acteur, $dossier) && $acteur->role === RoleUtilisateur::Gerant;
    }

    private function memeTenant(User $acteur, Dossier $dossier): bool
    {
        return $acteur->tenant_id === $dossier->tenant_id;
    }
}
