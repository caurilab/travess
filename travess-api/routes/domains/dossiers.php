<?php

declare(strict_types=1);

use App\Domains\Dossiers\Http\Controllers\DossierController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Dossiers — surface agent (docs/10 §3). Monté sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('dossiers', [DossierController::class, 'index']);
    Route::post('dossiers', [DossierController::class, 'store']);
    Route::get('dossiers/{dossier}', [DossierController::class, 'show']);
    Route::patch('dossiers/{dossier}', [DossierController::class, 'update']);
    Route::post('dossiers/{dossier}/cloturer', [DossierController::class, 'cloturer']);
    Route::put('dossiers/{dossier}/agents', [DossierController::class, 'assigner']);
    Route::get('dossiers/{dossier}/audit', [DossierController::class, 'audit']);
});
