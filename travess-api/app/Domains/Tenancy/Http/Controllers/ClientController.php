<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Http\Controllers;

use App\Domains\Tenancy\Actions\CreerClient;
use App\Domains\Tenancy\Actions\MettreAJourClient;
use App\Domains\Tenancy\Http\Requests\StoreClientRequest;
use App\Domains\Tenancy\Http\Requests\UpdateClientRequest;
use App\Domains\Tenancy\Http\Resources\ClientResource;
use App\Domains\Tenancy\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Clients (donneurs d'ordre), surface agent. Lectures au contrôleur, mutations
 * déléguées aux Actions (auditées). Tenant garanti par RLS + TenantScope.
 */
final class ClientController
{
    public function index(Request $requete): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Client::class);

        $clients = QueryBuilder::for(Client::class)
            ->allowedFilters(
                AllowedFilter::partial('nom'),
                AllowedFilter::exact('est_self'),
            )
            ->allowedSorts('nom', 'created_at')
            ->defaultSort('nom')
            ->paginate(min(100, max(1, (int) $requete->integer('per_page', 25))))
            ->appends($requete->query());

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $requete, CreerClient $creer): JsonResponse
    {
        Gate::authorize('create', Client::class);

        return ClientResource::make($creer->executer($requete->validated()))
            ->response()->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        Gate::authorize('view', $client);

        return ClientResource::make($client);
    }

    public function update(UpdateClientRequest $requete, Client $client, MettreAJourClient $miseAJour): ClientResource
    {
        Gate::authorize('update', $client);

        return ClientResource::make($miseAJour->executer($client, $requete->validated()));
    }
}
