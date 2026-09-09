<?php

declare(strict_types=1);

use App\Domains\Identity\Http\Controllers\DeuxFacteursController;
use App\Domains\Identity\Http\Controllers\SessionController;
use App\Domains\Identity\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Identity — authentification et utilisateurs. Monté sous /api/v1.
*/

// Public (pré-auth). Limité en débit pour contrer le bourrage d'identifiants.
Route::post('auth/login', [SessionController::class, 'login'])
    ->middleware('throttle:login');

// Protégé : jeton Sanctum + contexte tenant.
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('auth/me', [SessionController::class, 'me']);
    Route::post('auth/logout', [SessionController::class, 'logout']);
    Route::post('auth/refresh', [SessionController::class, 'refresh']);

    Route::post('auth/2fa/activer', [DeuxFacteursController::class, 'activer']);
    Route::post('auth/2fa/confirmer', [DeuxFacteursController::class, 'confirmer']);
    Route::post('auth/2fa/desactiver', [DeuxFacteursController::class, 'desactiver']);

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);
});
