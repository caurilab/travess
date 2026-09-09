<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Enums;

/**
 * Statut d'avancement d'une étape de workflow.
 */
enum StatutEtape: string
{
    case AFaire = 'a_faire';
    case EnCours = 'en_cours';
    case Fait = 'fait';
    case EnRetard = 'en_retard';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
