<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tracking\Jobs\PollerConteneur;
use App\Domains\Tracking\Jobs\PollerTrackingTenant;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Sélection du réveil (docs/08 §2) : le job tenant ne dispatche un poll que pour
 * les conteneurs ÉCHUS et ACTIFS. Un conteneur rendu, sans franchise active, ou
 * dont le prochain poll est futur, n'est jamais réveillé — c'est le cœur de
 * l'économie d'appels.
 */
final class PollerTrackingTenantTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private function franchiseActive(Conteneur $conteneur): void
    {
        Franchise::factory()->create([
            'conteneur_id' => $conteneur->id,
            'type' => TypeFranchise::Surestaries,
            'actif' => true,
        ]);
    }

    public function test_seuls_les_conteneurs_echus_et_actifs_sont_dispatches(): void
    {
        Bus::fake();
        $tenant = Tenant::factory()->create();

        $du = $this->pourTenant($tenant, function (): Conteneur {
            // A — franchise active, jamais suivi → premier poll dû.
            $a = Conteneur::factory()->create(['statut' => StatutConteneur::ATraiter]);
            $this->franchiseActive($a);

            // C — franchise active mais prochain poll dans le futur → pas dû.
            $c = Conteneur::factory()->create(['statut' => StatutConteneur::ATraiter]);
            $this->franchiseActive($c);
            SuiviTracking::factory()->create([
                'conteneur_id' => $c->id,
                'prochain_poll_prevu' => CarbonImmutable::now()->addDays(2),
                'captured_at' => CarbonImmutable::now(),
            ]);

            // D — échu par nature mais AUCUNE franchise active → hors périmètre.
            $d = Conteneur::factory()->create(['statut' => StatutConteneur::ATraiter]);
            Franchise::factory()->create(['conteneur_id' => $d->id, 'actif' => false]);

            // R — rendu : jamais réveillé, même avec une franchise active.
            $r = Conteneur::factory()->create(['statut' => StatutConteneur::Rendu]);
            $this->franchiseActive($r);

            return $a;
        });

        (new PollerTrackingTenant($tenant->id))->handle();

        // Un seul dispatch, et c'est bien le conteneur dû.
        Bus::assertDispatchedTimes(PollerConteneur::class, 1);
        Bus::assertDispatched(PollerConteneur::class, fn (PollerConteneur $job): bool => $job->conteneurId === $du->id);
    }

    public function test_conteneur_dont_le_prochain_poll_est_null_n_est_pas_reveille(): void
    {
        Bus::fake();
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            // Marqueur « ne plus poller » (prochain_poll_prevu null) : jamais dû.
            $c = Conteneur::factory()->create(['statut' => StatutConteneur::Enleve]);
            $this->franchiseActive($c);
            SuiviTracking::factory()->create([
                'conteneur_id' => $c->id,
                'prochain_poll_prevu' => null,
                'captured_at' => CarbonImmutable::now(),
            ]);
        });

        (new PollerTrackingTenant($tenant->id))->handle();

        Bus::assertNotDispatched(PollerConteneur::class);
    }
}
