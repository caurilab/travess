<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Controllers;

use App\Domains\Dossiers\Actions\MettreAJourEtape;
use App\Domains\Dossiers\Actions\ReordonnerEtapes;
use App\Domains\Dossiers\Http\Requests\ReordonnerEtapesRequest;
use App\Domains\Dossiers\Http\Requests\UpdateEtapeRequest;
use App\Domains\Dossiers\Http\Resources\DossierResource;
use App\Domains\Dossiers\Http\Resources\EtapeResource;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Models\Etape;
use Illuminate\Support\Facades\Gate;

/**
 * Étapes de workflow d'un dossier (docs/10 §4).
 */
final class EtapeController
{
    public function update(UpdateEtapeRequest $requete, Etape $etape, MettreAJourEtape $miseAJour): EtapeResource
    {
        Gate::authorize('update', $etape);

        return EtapeResource::make($miseAJour->executer($etape, $requete->validated()));
    }

    public function reordonner(ReordonnerEtapesRequest $requete, Dossier $dossier, ReordonnerEtapes $reordonner): DossierResource
    {
        Gate::authorize('update', $dossier);

        return DossierResource::make($reordonner->executer($dossier, $requete->validated()['ordre']));
    }
}
