<?php

declare(strict_types=1);

use App\Domains\Conteneurs\Http\Controllers\BlController;
use App\Domains\Conteneurs\Http\Controllers\ConteneurController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Conteneurs — BL et conteneurs (docs/10 §5). Monté sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    // Connaissements
    Route::post('bl', [BlController::class, 'store']);
    Route::get('bl/{bl}', [BlController::class, 'show']);

    // Conteneurs — « valider » avant la route paramétrée pour éviter la collision.
    Route::get('conteneurs/valider', [ConteneurController::class, 'valider']);
    Route::post('conteneurs', [ConteneurController::class, 'store']);
    Route::get('conteneurs/{conteneur}', [ConteneurController::class, 'show']);
    Route::patch('conteneurs/{conteneur}', [ConteneurController::class, 'update']);
});
