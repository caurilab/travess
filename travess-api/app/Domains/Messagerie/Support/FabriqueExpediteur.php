<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Support;

use App\Domains\Messagerie\Adapters\ExpediteurFactice;
use App\Domains\Messagerie\Contracts\ExpediteurMessage;
use App\Domains\Messagerie\Enums\CanalMessage;
use Illuminate\Contracts\Container\Container;

/**
 * Résout l'expéditeur d'un canal. En mode factice, un unique expéditeur couvre
 * tous les canaux (il enregistre le canal de chaque envoi). En mode réel, on
 * branchera un expéditeur par canal (email réel, WhatsApp, SMS).
 */
final class FabriqueExpediteur
{
    public function __construct(private readonly Container $container) {}

    public function pour(CanalMessage $canal): ExpediteurMessage
    {
        if (config('messagerie.driver') !== 'reel') {
            return $this->container->make(ExpediteurFactice::class);
        }

        // Expéditeurs réels par canal (7.2b+). Repli factice tant qu'absents.
        return $this->container->make(ExpediteurFactice::class);
    }
}
