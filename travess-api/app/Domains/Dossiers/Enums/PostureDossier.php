<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Enums;

/**
 * Posture d'un dossier (ADR-013, Lot 7.3).
 *
 * - autonome : créé et détenu par un compte client dans son propre workspace
 *   (vue étendue, RLS tenant normale, aucun acces_dossier requis).
 * - gere_par_transitaire : détenu par un transitaire (surface agent). Le client
 *   éventuel n'y a qu'un acces_dossier « limite ».
 *
 * Invariant : autonome ⟺ tenant propriétaire type=client.
 */
enum PostureDossier: string
{
    case Autonome = 'autonome';
    case GereParTransitaire = 'gere_par_transitaire';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
