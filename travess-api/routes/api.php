<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Routes API — /api/v1
|--------------------------------------------------------------------------
|
| Ce fichier est monté avec le préfixe « api » et le groupe de middleware
| « api ». On y ajoute le préfixe de version « v1 », puis on charge le
| fichier de routes de chaque domaine (routes/domains/<domaine>.php) qui
| existe. Chaque domaine applique lui-même ses middleware (auth, tenant…).
|
*/

Route::prefix('v1')->group(function (): void {
    foreach (config('domains.list', []) as $domaine) {
        $fichier = __DIR__.'/domains/'.Str::kebab($domaine).'.php';

        if (is_file($fichier)) {
            require $fichier;
        }
    }
});
