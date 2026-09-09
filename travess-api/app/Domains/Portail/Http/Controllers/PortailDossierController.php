<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Controllers;

use App\Domains\Portail\Http\Resources\DossierPortailResource;
use App\Domains\Portail\Policies\PartageDossierPolicy;
use App\Domains\Portail\Services\LectureDossiersPartages;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Surface portail client (ADR-013, sous-lot 7.1) : vue LIMITÉE (BL + parcours).
 * On ne réutilise jamais la surface agent ; la lecture passe par le read model
 * (RLS de partage) et la projection par les Resources portail (liste blanche).
 * Le contrôleur reste fin.
 */
final class PortailDossierController
{
    public function index(LectureDossiersPartages $lecture): AnonymousResourceCollection
    {
        return DossierPortailResource::collection($lecture->accessibles());
    }

    public function show(
        string $dossier,
        Request $requete,
        LectureDossiersPartages $lecture,
        PartageDossierPolicy $policy,
    ): DossierPortailResource {
        // La RLS de partage a déjà filtré : un dossier non octroyé revient null.
        $modele = $lecture->trouver($dossier);

        // Défense applicative en complément (octroi actif nominatif). Fail-closed
        // → 404 (on ne divulgue pas l'existence d'un dossier d'autrui).
        if ($modele === null || ! $policy->voir($requete->user(), $modele)) {
            throw new HttpException(404, 'Dossier introuvable.');
        }

        return DossierPortailResource::make($modele);
    }
}
