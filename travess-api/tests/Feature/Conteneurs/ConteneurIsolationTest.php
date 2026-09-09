<?php

declare(strict_types=1);

namespace Tests\Feature\Conteneurs;

use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Conteneurs\Support\Iso6346;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Étanchéité inter-tenant du domaine Conteneurs (bl + conteneur + franchise) :
 * un enregistrement créé sous le tenant A est invisible depuis le tenant B.
 * Domaine sensible car il porte les montants de surestaries/détention.
 */
final class ConteneurIsolationTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    public function test_bl_conteneur_et_franchise_sont_scopes_par_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        [$bl, $conteneur, $franchise] = $this->pourTenant($a, function (): array {
            $bl = Bl::factory()->create();
            $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id]);
            $franchise = Franchise::factory()->create(['conteneur_id' => $conteneur->id]);

            return [$bl, $conteneur, $franchise];
        });

        // Depuis A : tout est visible.
        $this->pourTenant($a, function () use ($bl, $conteneur, $franchise): void {
            $this->assertNotNull(Bl::find($bl->id));
            $this->assertNotNull(Conteneur::find($conteneur->id));
            $this->assertNotNull(Franchise::find($franchise->id));
            $this->assertSame(1, Conteneur::count());
        });

        // Depuis B : rien n'est visible.
        $this->pourTenant($b, function () use ($bl, $conteneur, $franchise): void {
            $this->assertNull(Bl::find($bl->id));
            $this->assertNull(Conteneur::find($conteneur->id));
            $this->assertNull(Franchise::find($franchise->id));
            $this->assertSame(0, Conteneur::count());
        });
    }

    public function test_le_numero_de_conteneur_genere_est_valide_iso_6346(): void
    {
        $a = Tenant::factory()->create();

        $conteneur = $this->pourTenant($a, fn () => Conteneur::factory()->create());

        $this->assertTrue(Iso6346::estValide($conteneur->numero));
    }
}
