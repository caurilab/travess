<?php

declare(strict_types=1);

namespace App\Domains\Portail\Policies;

use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations de la surface autonome (ADR-013, 7.3a). Un compte client agit
 * sur SES dossiers autonomes uniquement. Le garde « posture=autonome » est
 * central : dès qu'un dossier bascule « gere_par_transitaire », la surface
 * autonome ne l'écrit plus (fail-closed) — il ne reste que la vue limitée.
 */
final class DossierAutonomePolicy
{
    public function create(User $acteur): bool
    {
        return $acteur->role === RoleUtilisateur::Client;
    }

    public function view(User $acteur, Dossier $dossier): bool
    {
        return $this->gererAutonome($acteur, $dossier);
    }

    public function modifier(User $acteur, Dossier $dossier): bool
    {
        return $this->gererAutonome($acteur, $dossier);
    }

    private function gererAutonome(User $acteur, Dossier $dossier): bool
    {
        return $acteur->tenant_id === $dossier->tenant_id
            && $acteur->role === RoleUtilisateur::Client
            && $dossier->posture === PostureDossier::Autonome;
    }
}
