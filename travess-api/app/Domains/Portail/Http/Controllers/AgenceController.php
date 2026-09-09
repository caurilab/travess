<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Controllers;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Portail\Actions\DefinirVisibiliteAnnuaire;
use App\Domains\Portail\Http\Requests\AnnuaireVisibiliteRequest;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Réglages d'agence côté transitaire (ADR-013, 7.4) : visibilité dans l'annuaire
 * de la plateforme (opt-in). Réservé au gérant. Contrôleur fin.
 */
final class AgenceController
{
    public function definirVisibiliteAnnuaire(AnnuaireVisibiliteRequest $requete, DefinirVisibiliteAnnuaire $action): JsonResponse
    {
        if ($requete->user()->role !== RoleUtilisateur::Gerant) {
            throw new HttpException(403, 'Réservé au gérant.');
        }

        $public = $action->executer((bool) $requete->validated('public'));

        return response()->json(['annuaire_public' => $public]);
    }
}
