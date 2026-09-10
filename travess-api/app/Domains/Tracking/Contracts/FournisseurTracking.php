<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Contracts;

use App\Domains\Tracking\Data\NavireData;
use App\Domains\Tracking\Data\StatsQuotaData;
use App\Domains\Tracking\Data\SuiviConteneurData;

/**
 * Fournisseur de tracking conteneur/navire, isolé derrière cette interface
 * (principe n°9). Le fournisseur réel (JSONCargo) est branché par config ; un
 * adaptateur factice permet de tout tester sans réseau ni clé.
 *
 * Chaque appel à `suivreConteneur`/`resoudreNavireImo` est FACTURÉ (1 appel) :
 * l'appelant est responsable de l'économie (scheduler + plafond).
 */
interface FournisseurTracking
{
    /**
     * E2 — numéros de conteneur d'un BL (inversion de saisie). `$armateurApi`
     * est le code fournisseur (Armateur::nom_api).
     *
     * @return list<string> numéros ISO 6346
     */
    public function conteneursDepuisBl(string $numeroBl, string $armateurApi): array;

    /** E1 — dernier suivi d'un conteneur. */
    public function suivreConteneur(string $numero, string $armateurApi): SuiviConteneurData;

    /** E6 — résolution du navire par IMO ou nom (retourne l'IMO fiable, principe n°7). */
    public function resoudreNavireImo(string $imoOuNom): NavireData;

    /** E10 — stats du quota mutualisé (plafond de sécurité). */
    public function statsQuota(): StatsQuotaData;
}
