<?php

declare(strict_types=1);

namespace Tests\Feature\Conteneurs;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

final class ConteneurApiTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * Crée tenant + gérant (acté) + un BL, et renvoie l'id du BL.
     *
     * @return array{Tenant, User, string}
     */
    private function contexteAvecBl(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        [$client, $armateur] = $this->pourTenant($tenant, fn (): array => [
            Client::factory()->create(),
            Armateur::factory()->create(),
        ]);

        Sanctum::actingAs($gerant);
        $dossierId = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        $blId = $this->postJson('/api/v1/bl', [
            'dossier_id' => $dossierId,
            'numero' => 'MEDUAA123456',
            'armateur_id' => $armateur->id,
            'navire_imo' => '9074729',
        ])->assertStatus(201)->json('data.id');

        return [$tenant, $gerant, $blId];
    }

    public function test_valider_confirme_un_numero_iso_6346(): void
    {
        $tenant = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($tenant)->create());

        $this->getJson('/api/v1/conteneurs/valider?numero=MSCU7390252')
            ->assertOk()
            ->assertJsonPath('data.valide', true)
            ->assertJsonPath('data.normalise', 'MSCU7390252');

        $this->getJson('/api/v1/conteneurs/valider?numero=mscu7390253')
            ->assertOk()
            ->assertJsonPath('data.valide', false);
    }

    public function test_creation_d_un_conteneur_valide(): void
    {
        [, , $blId] = $this->contexteAvecBl();

        $this->postJson('/api/v1/conteneurs', [
            'bl_id' => $blId,
            'numero' => 'mscu 7390252', // sera normalisé
            'type' => '40hc',
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.numero', 'MSCU7390252')
            ->assertJsonPath('data.statut', 'a_traiter');
    }

    public function test_un_numero_iso_invalide_est_rejete_par_le_serveur(): void
    {
        [, , $blId] = $this->contexteAvecBl();

        // Le client pourrait contourner sa validation ; le serveur, lui, refuse.
        $this->postJson('/api/v1/conteneurs', [
            'bl_id' => $blId,
            'numero' => 'MSCU7390253', // chiffre de contrôle incorrect
            'type' => '40',
        ])->assertStatus(422)->assertJsonValidationErrorFor('numero');
    }

    public function test_mise_a_jour_du_statut_conteneur(): void
    {
        [, , $blId] = $this->contexteAvecBl();
        $id = $this->postJson('/api/v1/conteneurs', ['bl_id' => $blId, 'numero' => 'MSCU7390252', 'type' => '40'])->json('data.id');

        $this->patchJson("/api/v1/conteneurs/{$id}", ['statut' => 'enleve'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'enleve');
    }

    public function test_un_chauffeur_ne_peut_pas_consulter_un_bl(): void
    {
        [$tenant, , $blId] = $this->contexteAvecBl();
        $chauffeur = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Chauffeur)->create();
        Sanctum::actingAs($chauffeur);

        $this->getJson("/api/v1/bl/{$blId}")->assertForbidden();
    }

    public function test_un_conteneur_d_un_autre_tenant_est_introuvable(): void
    {
        [, , $blId] = $this->contexteAvecBl();
        $idB = $this->postJson('/api/v1/conteneurs', ['bl_id' => $blId, 'numero' => 'MSCU7390252', 'type' => '40'])->json('data.id');

        $tenantA = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($tenantA)->role(RoleUtilisateur::Gerant)->create());

        $this->getJson("/api/v1/conteneurs/{$idB}")->assertNotFound();
    }
}
