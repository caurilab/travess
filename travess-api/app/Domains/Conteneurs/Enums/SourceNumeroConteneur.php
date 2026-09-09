<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Enums;

/**
 * Provenance du numéro de conteneur (traçabilité de la saisie).
 */
enum SourceNumeroConteneur: string
{
    case Manuel = 'manuel';
    case ImportBol = 'import_bol';
    case Scan = 'scan';
    case Ia = 'ia';

    /**
     * @return list<string>
     */
    public static function valeurs(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
