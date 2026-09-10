<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Controllers;

use App\Domains\Audit\Http\Resources\AuditLogResource;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Dossiers\Actions\AssignerAgents;
use App\Domains\Dossiers\Actions\CloturerDossier;
use App\Domains\Dossiers\Actions\CreerDossier;
use App\Domains\Dossiers\Actions\MettreAJourDossier;
use App\Domains\Dossiers\Http\Requests\AssignerAgentsRequest;
use App\Domains\Dossiers\Http\Requests\CloturerDossierRequest;
use App\Domains\Dossiers\Http\Requests\StoreDossierRequest;
use App\Domains\Dossiers\Http\Requests\UpdateDossierRequest;
use App\Domains\Dossiers\Http\Resources\DossierResource;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Surface agent des dossiers (docs/10 §3). Lectures au contrôleur, mutations
 * déléguées aux Actions (auditées, transactionnelles).
 */
final class DossierController
{
    public function index(Request $requete): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Dossier::class);

        // Le builder part du modèle déjà scopé par TenantScope + RLS : aucun
        // filtre n'expose tenant_id, l'allow-list est explicite.
        $dossiers = QueryBuilder::for(Dossier::class)
            ->allowedFilters(
                AllowedFilter::exact('sens'),
                AllowedFilter::exact('statut'),
                AllowedFilter::exact('client_id'),
                AllowedFilter::callback('agent', function ($query, $valeur): void {
                    $query->whereHas('agents', fn ($a) => $a->whereKey($valeur));
                }),
            )
            ->allowedSorts('reference', 'created_at')
            ->defaultSort('-created_at')
            ->with('client')
            ->paginate($this->parPage($requete))
            ->appends($requete->query());

        return DossierResource::collection($dossiers);
    }

    public function store(StoreDossierRequest $requete, CreerDossier $creer): JsonResponse
    {
        Gate::authorize('create', Dossier::class);

        $dossier = $creer->executer($requete->validated());

        return DossierResource::make($dossier)->response()->setStatusCode(201);
    }

    /**
     * GET /dossiers/statistiques — agrégats d'activité tenant-scopés (RLS +
     * TenantScope) pour l'écran Rapports : volumes par statut et par sens.
     */
    public function statistiques(): JsonResponse
    {
        Gate::authorize('viewAny', Dossier::class);

        $parStatut = Dossier::query()
            ->selectRaw('statut, count(*) as n')
            ->groupBy('statut')
            ->pluck('n', 'statut');

        $parSens = Dossier::query()
            ->selectRaw('sens, count(*) as n')
            ->groupBy('sens')
            ->pluck('n', 'sens');

        $statuts = ['ouvert', 'en_cours', 'bloque', 'cloture'];
        $sens = ['import', 'export'];

        $parStatutComplet = [];
        foreach ($statuts as $s) {
            $parStatutComplet[$s] = (int) ($parStatut[$s] ?? 0);
        }
        $parSensComplet = [];
        foreach ($sens as $s) {
            $parSensComplet[$s] = (int) ($parSens[$s] ?? 0);
        }

        $total = array_sum($parStatutComplet);

        return response()->json([
            'data' => [
                'total' => $total,
                'actifs' => $total - $parStatutComplet['cloture'],
                'par_statut' => $parStatutComplet,
                'par_sens' => $parSensComplet,
            ],
        ]);
    }

    public function show(Dossier $dossier): DossierResource
    {
        Gate::authorize('view', $dossier);

        $dossier->load(['client', 'etapes', 'agents', 'bls.conteneurs', 'documents']);

        return DossierResource::make($dossier);
    }

    public function update(UpdateDossierRequest $requete, Dossier $dossier, MettreAJourDossier $miseAJour): DossierResource
    {
        Gate::authorize('update', $dossier);

        return DossierResource::make($miseAJour->executer($dossier, $requete->validated()));
    }

    public function cloturer(CloturerDossierRequest $requete, Dossier $dossier, CloturerDossier $cloturer): DossierResource
    {
        Gate::authorize('cloturer', $dossier);

        return DossierResource::make($cloturer->executer($dossier, $requete->validated()['motif'] ?? null));
    }

    public function assigner(AssignerAgentsRequest $requete, Dossier $dossier, AssignerAgents $assigner): DossierResource
    {
        Gate::authorize('assigner', $dossier);

        return DossierResource::make($assigner->executer($dossier, $requete->validated()['agents']));
    }

    public function audit(Dossier $dossier): AnonymousResourceCollection
    {
        Gate::authorize('view', $dossier);

        $journal = AuditLog::where('entite', 'dossier')
            ->where('entite_id', $dossier->id)
            ->orderByDesc('at')
            ->cursorPaginate(50);

        return AuditLogResource::collection($journal);
    }

    private function parPage(Request $requete): int
    {
        return min(100, max(1, (int) $requete->integer('per_page', 25)));
    }
}
