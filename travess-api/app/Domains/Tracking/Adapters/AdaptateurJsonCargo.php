<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Adapters;

use App\Domains\Tracking\Contracts\FournisseurTracking;
use App\Domains\Tracking\Data\NavireData;
use App\Domains\Tracking\Data\StatsQuotaData;
use App\Domains\Tracking\Data\SuiviConteneurData;
use RuntimeException;

/**
 * Fournisseur JSONCargo RÉEL — REPORTÉ. Le contrat est figé ; l'implémentation
 * réseau reste à faire (docs/09) et est bloquée par des prérequis :
 *  - confirmer la base URL en HTTPS avant d'envoyer `x-api-key` (docs/09 §1) ;
 *  - mapper les `container_status` réels observés (docs/09 §4) ;
 *  - gérer 404 / timeouts / réponses aberrantes.
 * La clé mutualisée vit en .env (jamais dans le dépôt, jamais journalisée,
 * jamais recopiée dans le snapshot).
 */
final class AdaptateurJsonCargo implements FournisseurTracking
{
    public function conteneursDepuisBl(string $numeroBl, string $armateurApi): array
    {
        throw $this->nonImplemente();
    }

    public function suivreConteneur(string $numero, string $armateurApi): SuiviConteneurData
    {
        throw $this->nonImplemente();
    }

    public function resoudreNavireImo(string $imoOuNom): NavireData
    {
        throw $this->nonImplemente();
    }

    public function statsQuota(): StatsQuotaData
    {
        throw $this->nonImplemente();
    }

    private function nonImplemente(): RuntimeException
    {
        return new RuntimeException(
            "L'adaptateur JSONCargo n'est pas encore implémenté (HTTPS + mapping à confirmer). "
            .'Utilisez TRACKING_DRIVER=factice.'
        );
    }
}
