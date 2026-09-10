<?php

declare(strict_types=1);

use App\Domains\Tenancy\Http\Controllers\ClientController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Tenancy — clients (donneurs d'ordre), surface agent. Sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('clients', [ClientController::class, 'index']);
    Route::post('clients', [ClientController::class, 'store']);
    Route::get('clients/{client}', [ClientController::class, 'show']);
    Route::patch('clients/{client}', [ClientController::class, 'update']);
});
