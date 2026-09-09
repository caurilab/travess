<?php

declare(strict_types=1);

namespace App\Domains\Messagerie\Support;

use App\Domains\Messagerie\Adapters\ExpediteurDiffere;
use App\Domains\Messagerie\Adapters\ExpediteurEmail;
use App\Domains\Messagerie\Adapters\ExpediteurFactice;
use App\Domains\Messagerie\Contracts\ExpediteurMessage;
use App\Domains\Messagerie\Enums\CanalMessage;
use Illuminate\Contracts\Container\Container;

/**
 * Résout l'expéditeur d'un canal. En mode « factice » (défaut), un unique
 * expéditeur couvre tous les canaux (sans réseau). En mode « reel » : l'e-mail
 * est branché (7.2b) ; WhatsApp/SMS restent différés tant que les fournisseurs
 * agréés ne sont pas intégrés (7.2c).
 */
final class FabriqueExpediteur
{
    public function __construct(private readonly Container $container) {}

    public function pour(CanalMessage $canal): ExpediteurMessage
    {
        if (config('messagerie.driver') !== 'reel') {
            return $this->container->make(ExpediteurFactice::class);
        }

        return match ($canal) {
            CanalMessage::Email => $this->container->make(ExpediteurEmail::class),
            CanalMessage::Whatsapp, CanalMessage::Sms => $this->container->make(ExpediteurDiffere::class),
        };
    }
}
