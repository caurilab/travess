<?php

declare(strict_types=1);

use App\Domains\Tracking\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Tracking — suivi conteneur (docs/08–09). Monté sous /api/v1.
| Rafraîchissement à la demande mis en file (l'agent n'attend jamais l'externe).
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('conteneurs/{conteneur}/tracking', [TrackingController::class, 'dernier']);
    Route::post('conteneurs/{conteneur}/tracking/rafraichir', [TrackingController::class, 'rafraichir'])
        ->middleware('throttle:30,1');
});
