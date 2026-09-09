<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Data;

use App\Domains\Messagerie\Enums\StatutEnvoi;

/**
 * Résultat d'un envoi (statut + référence fournisseur éventuelle).
 */
final class ResultatEnvoi
{
    public function __construct(
        public readonly StatutEnvoi $statut,
        public readonly ?string $referenceExterne = null,
    ) {}
}
