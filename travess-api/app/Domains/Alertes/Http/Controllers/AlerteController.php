<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Http\Controllers;

use App\Domains\Alertes\Actions\MettreAJourAlerte;
use App\Domains\Alertes\Http\Requests\UpdateAlerteRequest;
use App\Domains\Alertes\Http\Resources\AlerteResource;
use App\Domains\Alertes\Models\Alerte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Alertes (docs/10 §8). Lecture filtrable + évolution de statut.
 */
final class AlerteController
{
    public function index(Request $requete): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Alerte::class);

        $alertes = QueryBuilder::for(Alerte::class)
            ->allowedFilters(
                AllowedFilter::exact('statut'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('dossier_id'),
            )
            ->allowedSorts('created_at')
            ->defaultSort('-created_at')
            ->paginate(min(100, max(1, (int) $requete->integer('per_page', 25))))
            ->appends($requete->query());

        return AlerteResource::collection($alertes);
    }

    public function update(UpdateAlerteRequest $requete, Alerte $alerte, MettreAJourAlerte $miseAJour): AlerteResource
    {
        Gate::authorize('update', $alerte);

        return AlerteResource::make($miseAJour->executer($alerte, $requete->validated()));
    }
}
