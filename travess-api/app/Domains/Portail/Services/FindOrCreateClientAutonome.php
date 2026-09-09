<?php

declare(strict_types=1);

namespace App\Domains\Portail\Services;

use App\Domains\Tenancy\Models\Client;

/**
 * Retourne (ou crée) la fiche « soi-même » du client autonome dans son propre
 * workspace (ADR-013, 7.3). Une seule fiche self par workspace client :
 * dans son espace, le compte client EST son propre donneur d'ordre, et
 * dossiers.client_id doit pointer une fiche du même tenant.
 */
final class FindOrCreateClientAutonome
{
    public function executer(string $nom): Client
    {
        // Scopé au tenant courant (BelongsToTenant) : le firstOrCreate ne peut
        // matcher/créer que dans le workspace du client authentifié.
        return Client::firstOrCreate(
            ['est_self' => true],
            ['nom' => $nom],
        );
    }
}
