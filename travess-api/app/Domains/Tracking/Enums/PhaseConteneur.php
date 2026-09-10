<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Enums;

/**
 * Phase fine du cycle d'un conteneur, telle que rapportée par le tracking.
 *
 * Plus granulaire que StatutConteneur (a_traiter/enleve/livre/rendu) : elle
 * porte l'information nécessaire au calcul du prochain poll (docs/08 §2.2), là
 * où le statut métier ne suffit pas (« en mer » vs « à l'approche »).
 */
enum PhaseConteneur: string
{
    case EnMer = 'en_mer';
    case Approche = 'approche';
    case Decharge = 'decharge';
    case Enleve = 'enleve';
    case Livre = 'livre';
    case Rendu = 'rendu';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
