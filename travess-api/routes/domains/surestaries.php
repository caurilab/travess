<?php

declare(strict_types=1);

use App\Domains\Surestaries\Http\Controllers\DashboardController;
use App\Domains\Surestaries\Http\Controllers\FranchiseController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Surestaries — franchises & tableaux de bord (docs/10 §7). Sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('conteneurs/{conteneur}/franchises', [FranchiseController::class, 'index']);
    Route::post('conteneurs/{conteneur}/franchises', [FranchiseController::class, 'definir']);
    Route::patch('franchises/{franchise}', [FranchiseController::class, 'ajuster']);

    Route::get('dashboard/argent-en-feu', [DashboardController::class, 'argentEnFeu']);
    Route::get('dashboard/surestaries-evitees', [DashboardController::class, 'surestariesEvitees']);
});
