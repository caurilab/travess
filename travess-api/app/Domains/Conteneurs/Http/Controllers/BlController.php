<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Controllers;

use App\Domains\Conteneurs\Actions\CreerBl;
use App\Domains\Conteneurs\Http\Requests\StoreBlRequest;
use App\Domains\Conteneurs\Http\Resources\BlResource;
use App\Domains\Conteneurs\Models\Bl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Connaissements (docs/10 §5). L'import automatique des conteneurs depuis un BL
 * (JSONCargo) relève du Lot 4 ; ici, création manuelle.
 */
final class BlController
{
    public function store(StoreBlRequest $requete, CreerBl $creer): JsonResponse
    {
        Gate::authorize('create', Bl::class);

        $bl = $creer->executer($requete->validated());

        return BlResource::make($bl)->response()->setStatusCode(201);
    }

    public function show(Bl $bl): BlResource
    {
        Gate::authorize('view', $bl);

        return BlResource::make($bl->load('conteneurs'));
    }
}
