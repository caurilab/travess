<?php

declare(strict_types=1);

use App\Domains\Correspondance\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Correspondance — messages armateur d'un dossier (docs/10 §12).
| Monté sous /api/v1. Envoi mis en file (202), l'agent n'attend jamais l'externe.
*/

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('dossiers/{dossier}/messages', [MessageController::class, 'index']);
    // Envoi plafonné (audit C1) : garde-fou anti-abus du canal sortant.
    Route::post('dossiers/{dossier}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:20,1');
    Route::post('dossiers/{dossier}/messages/brouillon', [MessageController::class, 'brouillon']);
    Route::get('messages/{message}', [MessageController::class, 'show']);
});
