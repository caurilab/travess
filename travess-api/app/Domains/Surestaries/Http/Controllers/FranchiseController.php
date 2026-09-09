<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Http\Controllers;

use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Surestaries\Actions\AjusterFranchise;
use App\Domains\Surestaries\Actions\DefinirFranchise;
use App\Domains\Surestaries\Http\Requests\StoreFranchiseRequest;
use App\Domains\Surestaries\Http\Requests\UpdateFranchiseRequest;
use App\Domains\Surestaries\Http\Resources\FranchiseResource;
use App\Domains\Surestaries\Services\RecalculFranchise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Franchises d'un conteneur (docs/10 §7). Lecture au contrôleur (recalcul frais
 * en mémoire, sans écriture — le snapshot persistant est rafraîchi par le job
 * planifié) ; mutations déléguées aux Actions.
 */
final class FranchiseController
{
    public function __construct(
        private readonly RecalculFranchise $recalcul,
    ) {}

    public function index(Conteneur $conteneur): AnonymousResourceCollection
    {
        Gate::authorize('view', $conteneur);

        $conteneur->loadMissing('bl.armateur');

        $franchises = $conteneur->franchises()->get()->each(function (Franchise $franchise) use ($conteneur): void {
            $franchise->setRelation('conteneur', $conteneur);
            $this->recalcul->recalculer($franchise); // en mémoire, pas de save() en lecture
        });

        return FranchiseResource::collection($franchises);
    }

    public function definir(StoreFranchiseRequest $requete, Conteneur $conteneur, DefinirFranchise $definir): JsonResponse
    {
        Gate::authorize('update', $conteneur);

        return FranchiseResource::make($definir->executer($conteneur, $requete->validated()))
            ->response()->setStatusCode(201);
    }

    public function ajuster(UpdateFranchiseRequest $requete, Franchise $franchise, AjusterFranchise $ajuster): FranchiseResource
    {
        Gate::authorize('update', $franchise);

        return FranchiseResource::make($ajuster->executer($franchise, $requete->validated()));
    }
}
