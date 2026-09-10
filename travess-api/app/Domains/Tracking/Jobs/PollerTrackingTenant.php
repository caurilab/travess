<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Jobs;

use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Shared\Jobs\JobTenantScoped;
use Illuminate\Support\Carbon;

/**
 * Réveille les conteneurs ÉCHUS et ACTIFS d'un tenant et dispatche un poll par
 * conteneur. C'est le cœur de l'économie d'appels (docs/08 §2) : on ne
 * sélectionne que ce qui a une chance d'avoir changé, jamais tout en boucle.
 * Tenant-scopé.
 */
final class PollerTrackingTenant extends JobTenantScoped
{
    protected function traiter(): void
    {
        $maintenant = Carbon::now();

        Conteneur::query()
            ->where('statut', '!=', StatutConteneur::Rendu->value)
            // Actif = au moins une franchise active (rendu/hors-franchise exclus).
            ->whereHas('franchises', fn ($q) => $q->where('actif', true))
            ->with('suivis')
            ->get()
            ->each(function (Conteneur $conteneur) use ($maintenant): void {
                if ($this->estDu($conteneur, $maintenant)) {
                    PollerConteneur::dispatch($this->tenantId, $conteneur->id);
                }
            });
    }

    private function estDu(Conteneur $conteneur, Carbon $maintenant): bool
    {
        $dernier = $conteneur->suivis->sortByDesc('captured_at')->first();

        // Jamais suivi → premier poll dû.
        if ($dernier === null) {
            return true;
        }
        // Marqueur « ne plus poller » (rendu / non-trackable) : jamais dû.
        if ($dernier->prochain_poll_prevu === null) {
            return false;
        }

        return $dernier->prochain_poll_prevu->lte($maintenant);
    }
}
