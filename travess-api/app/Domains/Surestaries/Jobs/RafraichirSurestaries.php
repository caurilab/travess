<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Jobs;

use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Surestaries\Services\GenererAlertes;
use App\Domains\Surestaries\Services\RecalculFranchise;
use App\Shared\Jobs\JobTenantScoped;

/**
 * Rafraîchit les surestaries d'UN tenant : recalcule et persiste les montants
 * des franchises (au passage du jour), puis génère les alertes de seuil.
 * S'exécute dans le contexte du tenant (JobTenantScoped).
 */
final class RafraichirSurestaries extends JobTenantScoped
{
    protected function traiter(): void
    {
        $recalcul = app(RecalculFranchise::class);

        Franchise::query()
            ->with('conteneur.bl.armateur')
            ->get()
            ->each(function (Franchise $franchise) use ($recalcul): void {
                $recalcul->recalculer($franchise);

                if ($franchise->isDirty()) {
                    $franchise->save();
                }
            });

        app(GenererAlertes::class)->pourTenantCourant();
    }
}
