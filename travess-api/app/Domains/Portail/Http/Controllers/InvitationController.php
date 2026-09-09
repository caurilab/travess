<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Controllers;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Actions\EmettreInvitation;
use App\Domains\Portail\Http\Requests\StoreInvitationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Émission d'invitations d'onboarding par le transitaire (ADR-013, 7.2).
 * Surface agent : auth + tenant + policy. Contrôleur fin.
 */
final class InvitationController
{
    public function emettre(Dossier $dossier, StoreInvitationRequest $requete, EmettreInvitation $emettre): JsonResponse
    {
        Gate::authorize('update', $dossier);

        $resultat = $emettre->executer($dossier, $requete->validated());

        return response()->json([
            'invitation' => [
                'id' => $resultat['invitation']->id,
                'statut' => $resultat['invitation']->statut->value,
                'canal' => $resultat['invitation']->canal->value,
                'expire_at' => $resultat['invitation']->expire_at->toIso8601String(),
            ],
            // Lien signé remis au transitaire (il peut aussi le partager lui-même).
            'lien' => $resultat['lien'],
        ], 202);
    }
}
