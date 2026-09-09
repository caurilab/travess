<?php

declare(strict_types=1);

use App\Domains\Portail\Http\Controllers\PortailDossierController;
use Illuminate\Support\Facades\Route;

/*
| Domaine Portail — surface client (ADR-013). Monté sous /api/v1/portail.
| Middlewares : auth:sanctum, « tenant » (workspace du client), « portail »
| (dépose app.portail_user_id pour la RLS de partage). Vue LIMITÉE : BL + parcours.
*/

Route::middleware(['auth:sanctum', 'tenant', 'portail'])->prefix('portail')->group(function (): void {
    Route::get('dossiers', [PortailDossierController::class, 'index']);
    Route::get('dossiers/{dossier}', [PortailDossierController::class, 'show']);
});
