<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Support;

use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Tracking\Enums\PhaseConteneur;

/**
 * Traduit la phase fine du tracking (PhaseConteneur) vers le statut métier
 * canonique (StatutConteneur). Plusieurs phases amont (en mer / approche /
 * déchargé) correspondent au même statut « à traiter » : le conteneur n'est pas
 * encore pris en charge côté transitaire. Seules les transitions canoniques
 * font bouger StatutConteneur.
 */
final class MappeurStatutConteneur
{
    public static function versStatut(PhaseConteneur $phase): StatutConteneur
    {
        return match ($phase) {
            PhaseConteneur::EnMer,
            PhaseConteneur::Approche,
            PhaseConteneur::Decharge => StatutConteneur::ATraiter,
            PhaseConteneur::Enleve => StatutConteneur::Enleve,
            PhaseConteneur::Livre => StatutConteneur::Livre,
            PhaseConteneur::Rendu => StatutConteneur::Rendu,
        };
    }
}
