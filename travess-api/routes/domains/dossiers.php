<?php

declare(strict_types=1);

use App\Domains\Dossiers\Http\Controllers\DossierController;
use App\Domains\Dossiers\Http\Controllers\EtapeController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Dossiers — surface agent (docs/10 §3). Monté sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('dossiers', [DossierController::class, 'index']);
    // Avant la route paramétrée {dossier}, sinon « statistiques » serait capturé.
    Route::get('dossiers/statistiques', [DossierController::class, 'statistiques']);
    Route::post('dossiers', [DossierController::class, 'store']);
    Route::get('dossiers/{dossier}', [DossierController::class, 'show']);
    Route::patch('dossiers/{dossier}', [DossierController::class, 'update']);
    Route::post('dossiers/{dossier}/cloturer', [DossierController::class, 'cloturer']);
    Route::put('dossiers/{dossier}/agents', [DossierController::class, 'assigner']);
    Route::get('dossiers/{dossier}/audit', [DossierController::class, 'audit']);

    // Étapes (docs/10 §4)
    Route::post('dossiers/{dossier}/etapes/reordonner', [EtapeController::class, 'reordonner']);
    Route::patch('etapes/{etape}', [EtapeController::class, 'update']);
});
