<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Controllers;

use App\Domains\Conteneurs\Actions\CreerBl;
use App\Domains\Conteneurs\Actions\CreerConteneur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Actions\CreerDossierAutonome;
use App\Domains\Portail\Http\Requests\StoreBlAutonomeRequest;
use App\Domains\Portail\Http\Requests\StoreConteneurAutonomeRequest;
use App\Domains\Portail\Http\Requests\StoreDossierAutonomeRequest;
use App\Domains\Portail\Http\Resources\BlPortailResource;
use App\Domains\Portail\Http\Resources\ConteneurPortailResource;
use App\Domains\Portail\Http\Resources\DossierAutonomeResource;
use App\Domains\Portail\Policies\DossierAutonomePolicy;
use App\Domains\Portail\Services\FindOrCreateArmateurAutonome;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Surface autonome du client (ADR-013, 7.3a) : créer et gérer SES dossiers dans
 * son propre workspace (vue étendue restreinte : dossier + BL + conteneurs +
 * parcours). Jamais la surface agent ; policy et Resources dédiées, RLS tenant
 * normale (le dossier appartient au client). Contrôleur fin.
 */
final class PortailAutonomeController
{
    public function __construct(private readonly DossierAutonomePolicy $policy) {}

    public function index(Request $requete): AnonymousResourceCollection
    {
        $dossiers = Dossier::query()->with(['etapes', 'bls'])->get();

        return DossierAutonomeResource::collection($dossiers);
    }

    public function store(StoreDossierAutonomeRequest $requete, CreerDossierAutonome $creer): DossierAutonomeResource
    {
        $this->autoriser($this->policy->create($requete->user()));

        $dossier = $creer->executer($requete->validated(), (string) $requete->user()->nom);

        return DossierAutonomeResource::make($dossier->load('etapes', 'bls'));
    }

    public function show(Dossier $dossier, Request $requete): DossierAutonomeResource
    {
        $this->autoriser($this->policy->view($requete->user(), $dossier));

        return DossierAutonomeResource::make($dossier->load(['etapes', 'bls.conteneurs.suivis']));
    }

    public function storeBl(
        Dossier $dossier,
        StoreBlAutonomeRequest $requete,
        FindOrCreateArmateurAutonome $armateurs,
        CreerBl $creerBl,
    ): JsonResponse {
        $this->autoriser($this->policy->modifier($requete->user(), $dossier));

        $armateur = $armateurs->executer((string) $requete->validated('armateur'));

        $bl = $creerBl->executer([
            'dossier_id' => $dossier->id,
            'numero' => $requete->validated('numero'),
            'armateur_id' => $armateur->id,
            'navire_nom' => $requete->validated('navire_nom'),
            'navire_imo' => $requete->validated('navire_imo'),
        ]);

        return BlPortailResource::make($bl->load('conteneurs'))->response()->setStatusCode(201);
    }

    public function storeConteneur(Bl $bl, StoreConteneurAutonomeRequest $requete, CreerConteneur $creerConteneur): JsonResponse
    {
        $this->autoriser($this->policy->modifier($requete->user(), $bl->dossier()->firstOrFail()));

        $conteneur = $creerConteneur->executer([
            'bl_id' => $bl->id,
            'numero' => $requete->validated('numero'),
            'type' => $requete->validated('type'),
        ]);

        return ConteneurPortailResource::make($conteneur->load('suivis'))->response()->setStatusCode(201);
    }

    private function autoriser(bool $autorise): void
    {
        if (! $autorise) {
            throw new HttpException(403, 'Action non autorisée sur cette surface.');
        }
    }
}
