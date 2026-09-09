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

final class DossierTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * @return array{Tenant, User, Client}
     */
    private function tenantGerantClient(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        $client = $this->pourTenant($tenant, fn (): Client => Client::factory()->create());

        return [$tenant, $gerant, $client];
    }

    public function test_creation_genere_la_reference_et_instancie_le_workflow(): void
    {
        [, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $reponse = $this->postJson('/api/v1/dossiers', [
            'sens' => 'import',
            'client_id' => $client->id,
        ]);

        $reponse->assertStatus(201)
            ->assertJsonPath('data.sens', 'import')
            ->assertJsonPath('data.statut', 'ouvert')
            ->assertJsonPath('data.reference', 'IMP-'.now()->format('Y').'-0001');

        // 8 étapes du workflow import, ordonnées, avec date prévisionnelle.
        $etapes = $reponse->json('data.etapes');
        $this->assertCount(8, $etapes);
        $this->assertSame(1, $etapes[0]['ordre']);
        $this->assertNotNull($etapes[0]['date_prevue']);
    }

    public function test_references_incrementales_par_tenant(): void
    {
        [, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $un = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id]);
        $deux = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id]);

        $this->assertSame('IMP-'.now()->format('Y').'-0001', $un->json('data.reference'));
        $this->assertSame('IMP-'.now()->format('Y').'-0002', $deux->json('data.reference'));
    }

    public function test_liste_filtrable_et_paginee(): void
    {
        [$tenant, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id]);
        $this->postJson('/api/v1/dossiers', ['sens' => 'export', 'client_id' => $client->id]);

        $this->getJson('/api/v1/dossiers')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/dossiers?filter[sens]=export')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sens', 'export');
    }

    public function test_detail_charge_etapes_et_client(): void
    {
        [, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $id = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        $this->getJson("/api/v1/dossiers/{$id}")
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonCount(8, 'data.etapes')
            ->assertJsonPath('data.finances.solde', 0);
    }

    public function test_mise_a_jour_du_statut_et_du_motif(): void
    {
        [, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $id = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        $this->patchJson("/api/v1/dossiers/{$id}", ['statut' => 'bloque', 'motif_blocage' => 'attente paiement client'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'bloque')
            ->assertJsonPath('data.motif_blocage', 'attente paiement client');

        // La clôture n'est pas acceptée par la mise à jour ordinaire.
        $this->patchJson("/api/v1/dossiers/{$id}", ['statut' => 'cloture'])->assertStatus(422);
    }

    public function test_cloture_manuelle_puis_refus_de_reclore(): void
    {
        [, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $id = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        $this->postJson("/api/v1/dossiers/{$id}/cloturer", ['motif' => 'livraison terminée'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'cloture');

        $this->postJson("/api/v1/dossiers/{$id}/cloturer")->assertStatus(422);
    }

    public function test_assignation_des_agents(): void
    {
        [$tenant, $gerant, $client] = $this->tenantGerantClient();
        $agent = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Agent)->create();
        Sanctum::actingAs($gerant);

        $id = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        $this->putJson("/api/v1/dossiers/{$id}/agents", ['agents' => [$agent->id]])
            ->assertOk()
            ->assertJsonPath('data.agents.0.id', $agent->id);
    }

    public function test_le_journal_d_audit_trace_les_mutations(): void
    {
        [, $gerant, $client] = $this->tenantGerantClient();
        Sanctum::actingAs($gerant);

        $id = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');
        $this->postJson("/api/v1/dossiers/{$id}/cloturer")->assertOk();

        $actions = collect($this->getJson("/api/v1/dossiers/{$id}/audit")->assertOk()->json('data'))
            ->pluck('action');

        $this->assertTrue($actions->contains('dossier.creation'));
        $this->assertTrue($actions->contains('dossier.cloture'));
    }

    public function test_un_dossier_d_un_autre_tenant_est_introuvable(): void
    {
        [, $gerantA] = $this->tenantGerantClient();

        [$tenantB, , $clientB] = $this->tenantGerantClient();
        $gerantB = User::factory()->pourTenant($tenantB)->role(RoleUtilisateur::Gerant)->create();
        Sanctum::actingAs($gerantB);
        $idB = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $clientB->id])->json('data.id');

        // L'acteur du tenant A ne voit pas le dossier de B (404, pas 403).
        Sanctum::actingAs($gerantA);
        $this->getJson("/api/v1/dossiers/{$idB}")->assertNotFound();
    }

    public function test_le_comptable_est_en_lecture_seule(): void
    {
        [$tenant, , $client] = $this->tenantGerantClient();
        $comptable = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Comptable)->create();
        Sanctum::actingAs($comptable);

        $this->getJson('/api/v1/dossiers')->assertOk();
        $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->assertForbidden();
    }
}
