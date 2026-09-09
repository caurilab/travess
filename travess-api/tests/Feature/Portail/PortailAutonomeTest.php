<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Client autonome & vue étendue (ADR-013, 7.3a) : un compte client crée et gère
 * SES dossiers dans son propre workspace (dossier + BL + conteneurs + parcours),
 * sur une surface dédiée — jamais la surface agent.
 */
final class PortailAutonomeTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $workspace;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Tenant::factory()->client()->create();
        $this->client = User::factory()->pourTenant($this->workspace)->role(RoleUtilisateur::Client)->create(['nom' => 'Awa Traoré']);
    }

    public function test_le_client_cree_gere_et_consulte_son_dossier(): void
    {
        Sanctum::actingAs($this->client);

        // Création : posture autonome + parcours (étapes) instancié.
        $dossierId = $this->postJson('/api/v1/portail/autonome/dossiers', ['sens' => 'import'])
            ->assertStatus(201)
            ->assertJsonPath('data.posture', PostureDossier::Autonome->value)
            ->assertJsonPath('data.sens', 'import')
            ->json('data.id');

        // Ajout d'un BL (armateur provisionné par nom) puis d'un conteneur.
        $this->postJson("/api/v1/portail/autonome/dossiers/{$dossierId}/bls", [
            'numero' => 'BL-AUTO-1',
            'armateur' => 'Maersk',
        ])->assertStatus(201);

        $blId = $this->getJson("/api/v1/portail/autonome/dossiers/{$dossierId}")
            ->assertOk()
            ->assertJsonPath('data.bls.0.numero', 'BL-AUTO-1')
            ->json('data.bls.0.id');

        $this->postJson("/api/v1/portail/autonome/bls/{$blId}/conteneurs", [
            'numero' => 'MSCU7390252',
            'type' => '40',
        ])->assertStatus(201);

        $this->getJson("/api/v1/portail/autonome/dossiers/{$dossierId}")
            ->assertOk()
            ->assertJsonPath('data.bls.0.conteneurs.0.numero', 'MSCU7390252')
            ->assertJsonCount(1, 'data.bls');

        // Le parcours (étapes) est présent.
        $this->assertNotEmpty(
            $this->getJson("/api/v1/portail/autonome/dossiers/{$dossierId}")->json('data.etapes'),
        );
    }

    public function test_le_client_ne_peut_pas_utiliser_la_surface_agent(): void
    {
        Sanctum::actingAs($this->client);

        // La surface agent exclut le rôle client (policy de lecture agent).
        $this->getJson('/api/v1/dossiers')->assertStatus(403);
    }

    public function test_un_transitaire_ne_peut_pas_utiliser_la_surface_autonome(): void
    {
        $transitaire = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($transitaire)->role(RoleUtilisateur::Gerant)->create());

        $this->postJson('/api/v1/portail/autonome/dossiers', ['sens' => 'import'])->assertStatus(403);
    }

    public function test_un_client_ne_voit_pas_les_dossiers_d_un_autre(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/portail/autonome/dossiers', ['sens' => 'import'])->assertStatus(201);

        // Autre compte client, autre workspace.
        $autreWorkspace = Tenant::factory()->client()->create();
        Sanctum::actingAs(User::factory()->pourTenant($autreWorkspace)->role(RoleUtilisateur::Client)->create());

        $this->getJson('/api/v1/portail/autonome/dossiers')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_une_seule_fiche_self_par_workspace(): void
    {
        Sanctum::actingAs($this->client);
        $this->postJson('/api/v1/portail/autonome/dossiers', ['sens' => 'import'])->assertStatus(201);
        $this->postJson('/api/v1/portail/autonome/dossiers', ['sens' => 'export'])->assertStatus(201);

        // Deux dossiers, mais une seule fiche client « self ».
        $this->pourTenant($this->workspace, function (): void {
            $this->assertSame(2, Dossier::withoutGlobalScope(TenantScope::class)->count());
            $this->assertSame(1, Client::where('est_self', true)->count());
        });
    }
}
