<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Data;

/**
 * Identité d'un navire résolue par le tracking. La clé fiable est l'IMO
 * (principe n°7) ; le nom seul est ambigu (plusieurs navires peuvent le
 * partager).
 */
final class NavireData
{
    public function __construct(
        public readonly string $imo,
        public readonly ?string $mmsi,
        public readonly ?string $nom,
    ) {}
}
