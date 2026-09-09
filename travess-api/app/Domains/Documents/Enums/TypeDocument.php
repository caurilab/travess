<?php

declare(strict_types=1);

namespace App\Domains\Documents\Enums;

/**
 * Nature d'un document rattaché à un dossier.
 */
enum TypeDocument: string
{
    case Bl = 'bl';
    case FactureCharges = 'facture_charges';
    case Do = 'do';
    case DeclarationDouane = 'declaration_douane';
    case BonLivraison = 'bon_livraison';
    case Autre = 'autre';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
