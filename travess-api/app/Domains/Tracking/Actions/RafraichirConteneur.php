<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Actions;

use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Tracking\Jobs\PollerConteneur;
use App\Domains\Tracking\Services\GardeQuotaTracking;
use App\Shared\Context\TenantContext;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Rafraîchissement du tracking d'un conteneur À LA DEMANDE : met le travail en
 * file (principe n°4 — l'agent n'attend jamais l'externe). Refuse si le plafond
 * de quota global est atteint (bascule manuelle assumée).
 */
final class RafraichirConteneur
{
    public function __construct(
        private readonly GardeQuotaTracking $garde,
        private readonly TenantContext $tenant,
    ) {}

    public function executer(Conteneur $conteneur): void
    {
        if (! $this->garde->autoriseAppel()) {
            throw new HttpException(429, 'Quota de tracking atteint : suivi en mode manuel pour le moment.');
        }

        PollerConteneur::dispatch($this->tenant->idOrFail(), (string) $conteneur->getKey(), true);
    }
}
