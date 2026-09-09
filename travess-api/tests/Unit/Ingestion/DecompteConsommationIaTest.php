<?php

declare(strict_types=1);

namespace Tests\Unit\Ingestion;

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Domains\Consommation\Models\ConsommationService;
use App\Domains\Documents\Models\ExtractionIa;
use App\Domains\Ingestion\Services\DecompteConsommationIa;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Décompte de consommation IA : réservation atomique bornée par le quota,
 * libération, enregistrement du coût, comptage par service et période.
 */
final class DecompteConsommationIaTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private function decompte(): DecompteConsommationIa
    {
        return app(DecompteConsommationIa::class);
    }

    public function test_reserver_borne_au_quota(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            $d = $this->decompte();

            $this->assertTrue($d->reserver(2));
            $this->assertTrue($d->reserver(2));
            $this->assertFalse($d->reserver(2)); // plafond atteint
            $this->assertSame(2, $d->quantiteDuMois());
        });
    }

    public function test_liberer_ne_descend_pas_sous_zero(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            $d = $this->decompte();
            $d->reserver(5);
            $d->liberer();
            $this->assertSame(0, $d->quantiteDuMois());

            $d->liberer(); // pas d'effet en dessous de 0
            $this->assertSame(0, $d->quantiteDuMois());
        });
    }

    public function test_enregistrer_pose_le_cout_sans_reincrémenter(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            $d = $this->decompte();
            $d->reserver(10); // compteur = 1

            $extraction = ExtractionIa::factory()->create(['cout_unite' => 0]);
            $d->enregistrer($extraction, 1);

            $this->assertSame('1.00', (string) $extraction->fresh()->cout_unite);
            $this->assertSame(1, $d->quantiteDuMois()); // inchangé (pas de double compte)
        });
    }

    public function test_quantite_filtre_service_et_periode(): void
    {
        $tenant = Tenant::factory()->create();

        $this->pourTenant($tenant, function (): void {
            // Même mois, autre service : ignoré.
            ConsommationService::factory()->create([
                'service' => ServiceConsomme::Tracking->value,
                'periode' => Carbon::now()->format('Y-m'),
                'quantite' => 7,
            ]);
            // IA mais mois précédent : ignoré.
            ConsommationService::factory()->create([
                'service' => ServiceConsomme::Ia->value,
                'periode' => Carbon::now()->subMonth()->format('Y-m'),
                'quantite' => 9,
            ]);

            $this->decompte()->reserver(10); // IA, mois courant → 1

            $this->assertSame(1, $this->decompte()->quantiteDuMois());
        });
    }
}
