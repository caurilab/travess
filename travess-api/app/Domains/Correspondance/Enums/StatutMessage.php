<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Enums;

/**
 * Cycle de vie d'un message de correspondance.
 *
 * `brouillon` (non persisté au premier lot, réservé), `en_file` (envoi dispatché),
 * `en_cours` (verrou d'envoi pris par le job — anti double-envoi), `envoye` /
 * `echec` (issue de l'envoi), `recu` (message entrant).
 */
enum StatutMessage: string
{
    case Brouillon = 'brouillon';
    case EnFile = 'en_file';
    case EnCours = 'en_cours';
    case Envoye = 'envoye';
    case Echec = 'echec';
    case Recu = 'recu';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
