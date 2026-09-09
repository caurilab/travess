<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Controllers;

use App\Domains\Conteneurs\Actions\CreerConteneur;
use App\Domains\Conteneurs\Actions\MettreAJourConteneur;
use App\Domains\Conteneurs\Http\Requests\StoreConteneurRequest;
use App\Domains\Conteneurs\Http\Requests\UpdateConteneurRequest;
use App\Domains\Conteneurs\Http\Resources\ConteneurResource;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Support\Iso6346;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Conteneurs (docs/10 §5). La validation ISO 6346 est faite côté serveur
 * (source de vérité) et côté client (retour immédiat via packages/shared-core).
 */
final class ConteneurController
{
    /**
     * GET /conteneurs/valider?numero= — écho de confiance serveur.
     */
    public function valider(Request $requete): JsonResponse
    {
        $requete->validate(['numero' => ['required', 'string']]);

        $normalise = Iso6346::normaliser((string) $requete->query('numero'));

        return response()->json([
            'data' => [
                'valide' => Iso6346::estValide($normalise),
                'normalise' => $normalise,
            ],
        ]);
    }

    public function store(StoreConteneurRequest $requete, CreerConteneur $creer): JsonResponse
    {
        Gate::authorize('create', Conteneur::class);

        $conteneur = $creer->executer($requete->validated());

        return ConteneurResource::make($conteneur)->response()->setStatusCode(201);
    }

    public function show(Conteneur $conteneur): ConteneurResource
    {
        Gate::authorize('view', $conteneur);

        return ConteneurResource::make($conteneur);
    }

    public function update(UpdateConteneurRequest $requete, Conteneur $conteneur, MettreAJourConteneur $miseAJour): ConteneurResource
    {
        Gate::authorize('update', $conteneur);

        return ConteneurResource::make($miseAJour->executer($conteneur, $requete->validated()));
    }
}
