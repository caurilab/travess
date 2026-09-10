<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Enums;

/**
 * Nature d'une demande adressée à l'armateur. Sert de gabarit au brouillon
 * pré-rempli (objet + corps) et de filtre du fil de correspondance.
 */
enum TypeDemande: string
{
    case RelanceSurestaries = 'relance_surestaries';
    case Reclamation = 'reclamation';
    case DemandeBl = 'demande_bl';
    case DemandeDo = 'demande_do';
    case Autre = 'autre';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
