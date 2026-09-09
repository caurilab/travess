<?php

declare(strict_types=1);

use App\Domains\Ingestion\Http\Controllers\ExtractionController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Ingestion — extraction documentaire par IA (docs/10). Monté sous
| /api/v1. L'IA propose (mise en file), l'humain valide (principes n°4 et n°5).
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('documents/{document}/extraction', [ExtractionController::class, 'lancer']);
    Route::get('documents/{document}/extraction', [ExtractionController::class, 'derniere']);
    Route::post('extractions/{extraction}/validation', [ExtractionController::class, 'valider']);
});
