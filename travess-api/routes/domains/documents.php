<?php

declare(strict_types=1);

use App\Domains\Documents\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Documents — dépôt et consultation (docs/10 §9). Monté sous /api/v1.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('documents', [DocumentController::class, 'store']);
    Route::get('documents/{document}', [DocumentController::class, 'show']);
    Route::get('documents/{document}/telecharger', [DocumentController::class, 'telecharger']);
});
