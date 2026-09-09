<?php

declare(strict_types=1);

namespace Tests\Feature\Surestaries;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

final class FranchiseApiTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-10'); // date d'évaluation figée pour un résultat déterministe
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crée le contexte (tenant + gérant acté) et un conteneur 40', renvoie son id.
     *
     * @return array{Tenant, string}
     */
    private function contexteAvecConteneur(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        [$client, $armateur] = $this->pourTenant($tenant, fn (): array => [
            Client::factory()->create(),
            Armateur::factory()->create(),
        ]);

        Sanctum::actingAs($gerant);
        $dossierId = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');
        $blId = $this->postJson('/api/v1/bl', ['dossier_id' => $dossierId, 'numero' => 'MEDUAA123456', 'armateur_id' => $armateur->id])->json('data.id');
        $conteneurId = $this->postJson('/api/v1/conteneurs', ['bl_id' => $blId, 'numero' => 'MSCU7390252', 'type' => '40'])->json('data.id');

        return [$tenant, $conteneurId];
    }

    public function test_definir_une_franchise_calcule_les_montants(): void
    {
        [, $conteneurId] = $this->contexteAvecConteneur();

        $this->postJson("/api/v1/conteneurs/{$conteneurId}/franchises", [
            'type' => 'surestaries',
            'date_debut' => '2026-01-01',
            'jours_francs' => 7,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.date_fin_franchise', '2026-01-07')
            ->assertJsonPath('data.montant_en_cours', 45000)
            ->assertJsonPath('data.montant_menacant', 60000)
            ->assertJsonPath('data.actif', true);
    }

    public function test_lister_les_franchises_recalcule_a_la_volee(): void
    {
        [, $conteneurId] = $this->contexteAvecConteneur();
        $this->postJson("/api/v1/conteneurs/{$conteneurId}/franchises", ['type' => 'surestaries', 'date_debut' => '2026-01-01', 'jours_francs' => 7]);

        $this->getJson("/api/v1/conteneurs/{$conteneurId}/franchises")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.montant_en_cours', 45000);
    }

    public function test_ajuster_une_franchise_recalcule(): void
    {
        [, $conteneurId] = $this->contexteAvecConteneur();
        $id = $this->postJson("/api/v1/conteneurs/{$conteneurId}/franchises", ['type' => 'surestaries', 'date_debut' => '2026-01-01', 'jours_francs' => 7])->json('data.id');

        $this->patchJson("/api/v1/franchises/{$id}", ['jours_francs' => 3])
            ->assertOk()
            ->assertJsonPath('data.date_fin_franchise', '2026-01-03')
            ->assertJsonPath('data.montant_en_cours', 135000);
    }

    public function test_une_franchise_d_un_autre_tenant_est_introuvable(): void
    {
        [, $conteneurId] = $this->contexteAvecConteneur();
        $idB = $this->postJson("/api/v1/conteneurs/{$conteneurId}/franchises", ['type' => 'surestaries', 'date_debut' => '2026-01-01', 'jours_francs' => 7])->json('data.id');

        $tenantA = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($tenantA)->role(RoleUtilisateur::Gerant)->create());

        $this->patchJson("/api/v1/franchises/{$idB}", ['jours_francs' => 2])->assertNotFound();
    }
}
