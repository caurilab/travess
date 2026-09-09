<?php

declare(strict_types=1);

use App\Domains\Alertes\Http\Controllers\AlerteController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Alertes (docs/10 §8). Monté sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('alertes', [AlerteController::class, 'index']);
    Route::patch('alertes/{alerte}', [AlerteController::class, 'update']);
});
