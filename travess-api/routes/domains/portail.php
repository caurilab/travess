<?php

declare(strict_types=1);

use App\Domains\Portail\Http\Controllers\InvitationController;
use App\Domains\Portail\Http\Controllers\OnboardingController;
use App\Domains\Portail\Http\Controllers\PortailDossierController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Portail (ADR-013). Monté sous /api/v1.
| Trois surfaces distinctes :
|  1. Client authentifié (vue limitée) — auth + tenant + portail.
|  2. Transitaire (émission d'invitations) — auth + tenant + policy.
|  3. Onboarding PUBLIC (réclamation/confirmation) — pas d'auth ; URL signée,
|     token secret, RLS bornée au token, OTP.
*/

// 1. Vue limitée du client.
Route::middleware(['auth:sanctum', 'tenant', 'portail'])->prefix('portail')->group(function (): void {
    Route::get('dossiers', [PortailDossierController::class, 'index']);
    Route::get('dossiers/{dossier}', [PortailDossierController::class, 'show']);
});

// 2. Émission d'invitations par le transitaire.
Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('dossiers/{dossier}/invitations', [InvitationController::class, 'emettre']);
});

// 3. Onboarding public. Le token voyage dans le CHEMIN ; le middleware
// « invitation » pose app.invitation_token_hash (RLS bornée à une ligne).
Route::prefix('portail')->group(function (): void {
    Route::get('invitations/{token}', [OnboardingController::class, 'montrer'])
        ->middleware(['signed', 'throttle:10,1', 'invitation'])
        ->name('portail.invitation.montrer');

    Route::post('invitations/{token}/confirmer', [OnboardingController::class, 'confirmer'])
        ->middleware(['throttle:10,1', 'invitation']);
});
