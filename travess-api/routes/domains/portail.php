<?php

declare(strict_types=1);

use App\Domains\Portail\Http\Controllers\InvitationController;
use App\Domains\Portail\Http\Controllers\OnboardingController;
use App\Domains\Portail\Http\Controllers\PortailAutonomeController;
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

// 1 bis. Surface autonome : le client crée et gère SES dossiers (vue étendue).
Route::middleware(['auth:sanctum', 'tenant', 'autonome'])->prefix('portail/autonome')->group(function (): void {
    Route::get('dossiers', [PortailAutonomeController::class, 'index']);
    Route::post('dossiers', [PortailAutonomeController::class, 'store']);
    Route::get('dossiers/{dossier}', [PortailAutonomeController::class, 'show']);
    Route::post('dossiers/{dossier}/bls', [PortailAutonomeController::class, 'storeBl']);
    Route::post('bls/{bl}/conteneurs', [PortailAutonomeController::class, 'storeConteneur']);
});

// 2. Émission d'invitations par le transitaire (throttle + plafond par tenant).
Route::middleware(['auth:sanctum', 'tenant', 'throttle:30,1'])->group(function (): void {
    Route::post('dossiers/{dossier}/invitations', [InvitationController::class, 'emettre']);
});

// 3. Onboarding public. Le token voyage dans le CHEMIN ; le middleware
// « invitation » pose app.invitation_token_hash (RLS bornée à une ligne).
Route::prefix('portail')->group(function (): void {
    Route::get('invitations/{token}', [OnboardingController::class, 'montrer'])
        ->middleware(['signed', 'throttle:10,1', 'invitation'])
        ->name('portail.invitation.montrer');

    // Non « signed » : le secret est le token 256 bits (dans le chemin), borné
    // par expire_at + OTP + usage unique. Émettre une seconde URL signée pour le
    // POST n'ajouterait pas de protection (le token EST déjà le secret).
    Route::post('invitations/{token}/confirmer', [OnboardingController::class, 'confirmer'])
        ->middleware(['throttle:10,1', 'invitation']);
});
