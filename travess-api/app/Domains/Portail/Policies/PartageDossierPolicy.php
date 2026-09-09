<?php

declare(strict_types=1);

namespace App\Domains\Portail\Policies;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Models\User;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Models\AccesDossier;

/**
 * Autorisation de consultation d'un dossier via un accès partagé (ADR-013).
 * Défense applicative en complément de la RLS de partage : exige un octroi ACTIF
 * nominatif pour l'utilisateur sur le dossier. Fail-closed (aucun octroi → refus,
 * traduit en 404 par le contrôleur pour ne pas divulguer l'existence).
 */
final class PartageDossierPolicy
{
    public function voir(User $acteur, Dossier $dossier): bool
    {
        return AccesDossier::query()
            ->where('dossier_id', $dossier->getKey())
            ->where('beneficiaire_user_id', $acteur->getKey())
            ->where('statut', StatutAcces::Actif->value)
            ->exists();
    }
}
