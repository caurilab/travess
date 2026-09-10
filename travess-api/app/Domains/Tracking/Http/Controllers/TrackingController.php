<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Http\Controllers;

use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Tracking\Actions\RafraichirConteneur;
use App\Domains\Tracking\Http\Resources\SuiviTrackingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Tracking conteneur, surface agent (docs/08–09). Contrôleur fin : le
 * rafraîchissement passe par une Action (mise en file), la lecture renvoie le
 * dernier suivi.
 */
final class TrackingController
{
    public function rafraichir(Conteneur $conteneur, RafraichirConteneur $rafraichir): JsonResponse
    {
        Gate::authorize('update', $conteneur);

        $rafraichir->executer($conteneur);

        return response()->json(['data' => ['statut' => 'en_file']], Response::HTTP_ACCEPTED);
    }

    public function dernier(Conteneur $conteneur): SuiviTrackingResource|JsonResponse
    {
        Gate::authorize('view', $conteneur);

        $suivi = $conteneur->suivis()->orderByDesc('captured_at')->first();

        if ($suivi instanceof SuiviTracking) {
            return SuiviTrackingResource::make($suivi);
        }

        return response()->json(['data' => null]);
    }
}
