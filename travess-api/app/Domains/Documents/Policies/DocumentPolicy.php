<?php

declare(strict_types=1);

namespace App\Domains\Documents\Policies;

use App\Domains\Documents\Models\Document;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;

/**
 * Autorisations sur les documents (gérant/agent en dépôt ; tous rôles agence en
 * consultation ; tenant revérifié).
 */
final class DocumentPolicy
{
    private const LECTURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent, RoleUtilisateur::Comptable];

    private const ECRITURE = [RoleUtilisateur::Gerant, RoleUtilisateur::Agent];

    public function create(User $acteur): bool
    {
        return in_array($acteur->role, self::ECRITURE, true);
    }

    public function view(User $acteur, Document $document): bool
    {
        return $acteur->tenant_id === $document->tenant_id
            && in_array($acteur->role, self::LECTURE, true);
    }

    /**
     * Lancer une extraction IA et valider son résultat (l'IA propose, l'humain
     * valide) : réservé aux rôles en écriture, tenant revérifié.
     */
    public function extraire(User $acteur, Document $document): bool
    {
        return $acteur->tenant_id === $document->tenant_id
            && in_array($acteur->role, self::ECRITURE, true);
    }
}
