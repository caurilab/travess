<?php

declare(strict_types=1);

namespace App\Domains\Portail\Enums;

/**
 * Niveau d'un accès partagé à un dossier (ADR-013).
 *
 * - limite  : client rattaché à un transitaire — BL + parcours uniquement.
 * - etendu  : client autonome — vue étendue sur son propre dossier.
 * - gestion : transitaire propriétaire — accès complet.
 */
enum NiveauAcces: string
{
    case Limite = 'limite';
    case Etendu = 'etendu';
    case Gestion = 'gestion';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
