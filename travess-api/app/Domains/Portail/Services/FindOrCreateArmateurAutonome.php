<?php

declare(strict_types=1);

namespace App\Domains\Portail\Services;

use App\Domains\Armateurs\Models\Armateur;

/**
 * Retourne (ou crée) une fiche armateur MINIMALE par nom dans le workspace du
 * client autonome (ADR-013, 7.3a). Le client n'a pas d'annuaire d'armateurs :
 * on matérialise une fiche indicative (non trackable, sans barème) suffisante
 * pour rattacher un BL. Le vrai armateur (barèmes, tracking) est du ressort du
 * transitaire, après une éventuelle assignation.
 */
final class FindOrCreateArmateurAutonome
{
    public function executer(string $nom): Armateur
    {
        return Armateur::firstOrCreate(
            ['nom' => $nom],
            ['trackable' => false],
        );
    }
}
