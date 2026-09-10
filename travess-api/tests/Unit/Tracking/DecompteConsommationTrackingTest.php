<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Domains\Consommation\Models\ConsommationService;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tracking\Services\DecompteConsommationTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Décompte de consommation tracking (docs/08 §4), tenant-scopé. Ici on COMPTE
 * simplement (le plafond dur est global, cf. GardeQuotaTracking) : chaque appel
 * facturé s'ajoute au mois courant, un échec fournisseur le libère.
 */
final class DecompteConsommationTrackingTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private function decompte(): DecompteConsommationTracking
    {
        return app(DecompteConsommationTracking::class);
    }

    public function test_compter_incremente_le_mois_courant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            $d = $this->decompte();
            $d->compter();
            $d->compter();

            $this->assertSame(2, $d->quantiteDuMois());
        });
    }

    public function test_liberer_rend_l_unite_sans_descendre_sous_zero(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            $d = $this->decompte();
            $d->compter();
            $d->liberer();
            $this->assertSame(0, $d->quantiteDuMois());

            $d->liberer(); // plancher à 0
            $this->assertSame(0, $d->quantiteDuMois());
        });
    }

    public function test_compte_sur_le_service_tracking_uniquement(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            // Autre service, même mois : ignoré par le décompte tracking.
            ConsommationService::factory()->create([
                'service' => ServiceConsomme::Ia->value,
                'periode' => Carbon::now()->format('Y-m'),
                'quantite' => 9,
            ]);
            // Tracking mais mois précédent : ignoré.
            ConsommationService::factory()->create([
                'service' => ServiceConsomme::Tracking->value,
                'periode' => Carbon::now()->subMonth()->format('Y-m'),
                'quantite' => 4,
            ]);

            $this->decompte()->compter();

            $this->assertSame(1, $this->decompte()->quantiteDuMois());
        });
    }

    public function test_le_decompte_est_isole_par_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->pourTenant($a, fn () => $this->decompte()->compter(3));

        // B ne voit rien de la consommation de A.
        $this->pourTenant($b, fn () => $this->assertSame(0, $this->decompte()->quantiteDuMois()));
        $this->pourTenant($a, fn () => $this->assertSame(3, $this->decompte()->quantiteDuMois()));
    }
}
