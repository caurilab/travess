<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Policies;

use App\Domains\Correspondance\Models\Message;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur la correspondance armateur.
 *
 * Lecture : tous les rôles agence. Envoi : gérant/agent (le comptable consulte
 * mais n'écrit pas à l'armateur). Le tenant est garanti en amont (middleware
 * tenant + scope du dossier lié à la route) ; `view` le revérifie en défense en
 * profondeur.
 */
final class MessagePolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ENVOI = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function viewAny(User $acteur): bool
    {
        return in_array($acteur->role, self::LECTURE, true);
    }

    public function view(User $acteur, Message $message): bool
    {
        return $acteur->tenant_id === $message->tenant_id
            && in_array($acteur->role, self::LECTURE, true);
    }

    public function create(User $acteur): bool
    {
        return in_array($acteur->role, self::ENVOI, true);
    }
}
