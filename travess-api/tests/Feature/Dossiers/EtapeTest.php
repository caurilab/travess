<?php

declare(strict_types=1);

namespace Tests\Feature\Dossiers;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

final class EtapeTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * @return array{Tenant, User, array<string, mixed>}
     */
    private function dossierAvecEtapes(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        $client = $this->pourTenant($tenant, fn (): Client => Client::factory()->create());

        Sanctum::actingAs($gerant);
        $dossier = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data');

        return [$tenant, $gerant, $dossier];
    }

    public function test_passer_une_etape_a_fait_horodate_la_date_reelle(): void
    {
        [, , $dossier] = $this->dossierAvecEtapes();
        $etapeId = $dossier['etapes'][0]['id'];

        $this->patchJson("/api/v1/etapes/{$etapeId}", ['statut' => 'fait'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'fait')
            ->assertJsonPath('data.date_reelle', now()->toDateString());
    }

    public function test_edition_du_sla(): void
    {
        [, , $dossier] = $this->dossierAvecEtapes();
        $etapeId = $dossier['etapes'][0]['id'];

        $this->patchJson("/api/v1/etapes/{$etapeId}", ['sla_jours' => 9])
            ->assertOk()
            ->assertJsonPath('data.sla_jours', 9);
    }

    public function test_reordonnancement(): void
    {
        [, , $dossier] = $this->dossierAvecEtapes();
        $ids = array_column($dossier['etapes'], 'id');
        $inverse = array_reverse($ids);

        $reponse = $this->postJson("/api/v1/dossiers/{$dossier['id']}/etapes/reordonner", ['ordre' => $inverse])
            ->assertOk();

        $this->assertSame($inverse, array_column($reponse->json('data.etapes'), 'id'));
    }

    public function test_reordonnancement_incomplet_rejete(): void
    {
        [, , $dossier] = $this->dossierAvecEtapes();
        $ids = array_column($dossier['etapes'], 'id');

        $this->postJson("/api/v1/dossiers/{$dossier['id']}/etapes/reordonner", ['ordre' => [$ids[0]]])
            ->assertStatus(422);
    }

    public function test_une_etape_d_un_autre_tenant_est_introuvable(): void
    {
        [, , $dossierB] = $this->dossierAvecEtapes();
        $etapeIdB = $dossierB['etapes'][0]['id'];

        // Nouvel acteur, autre tenant.
        $tenantA = Tenant::factory()->create();
        $gerantA = User::factory()->pourTenant($tenantA)->role(RoleUtilisateur::Gerant)->create();
        Sanctum::actingAs($gerantA);

        $this->patchJson("/api/v1/etapes/{$etapeIdB}", ['statut' => 'fait'])->assertNotFound();
    }
}
