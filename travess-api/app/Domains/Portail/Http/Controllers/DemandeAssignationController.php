<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Controllers;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Portail\Actions\AccepterAssignation;
use App\Domains\Portail\Actions\RefuserAssignation;
use App\Domains\Portail\Enums\StatutDemande;
use App\Domains\Portail\Http\Requests\RefuserAssignationRequest;
use App\Domains\Portail\Http\Resources\DemandeAssignationResource;
use App\Domains\Portail\Models\DemandeAssignation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Boîte de réception et décision des demandes d'assignation, côté transitaire
 * (ADR-013, 7.3b) — surface agent, réservée au gérant (accepter = prendre la
 * propriété du dossier). La RLS ne montre que les demandes visant ce tenant.
 * Pas de route-model binding (il précède le middleware tenant) : on charge la
 * demande dans le contrôleur, sous contexte tenant établi.
 */
final class DemandeAssignationController
{
    public function index(Request $requete): AnonymousResourceCollection
    {
        $demandes = DemandeAssignation::query()
            ->where('statut', StatutDemande::EnAttente->value)
            ->latest()
            ->get();

        return DemandeAssignationResource::collection($demandes);
    }

    public function accepter(string $demande, Request $requete, AccepterAssignation $accepter): JsonResponse
    {
        $modele = $this->demandeGerable($demande, $requete);

        return response()->json($accepter->executer($modele));
    }

    public function refuser(string $demande, RefuserAssignationRequest $requete, RefuserAssignation $refuser): DemandeAssignationResource
    {
        $modele = $this->demandeGerable($demande, $requete);

        return DemandeAssignationResource::make($refuser->executer($modele, $requete->validated('motif')));
    }

    private function demandeGerable(string $id, Request $requete): DemandeAssignation
    {
        if ($requete->user()->role !== RoleUtilisateur::Gerant) {
            throw new HttpException(403, 'Réservé au gérant.');
        }

        // RLS : seule une demande visant le tenant courant est visible → 404 sinon.
        $demande = DemandeAssignation::query()->whereKey($id)->first();

        if ($demande === null) {
            throw new HttpException(404, 'Demande introuvable.');
        }

        return $demande;
    }
}
