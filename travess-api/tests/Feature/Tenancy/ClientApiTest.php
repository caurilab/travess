<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * CRUD des clients (donneurs d'ordre), surface agent : création (agence, jamais
 * « self »), liste filtrable, mise à jour, isolation tenant et matrice de rôles.
 */
final class ClientApiTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * @return array{Tenant, User}
     */
    private function tenantGerant(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();

        return [$tenant, $gerant];
    }

    public function test_creation_client_par_agence_nest_jamais_self(): void
    {
        [, $gerant] = $this->tenantGerant();
        Sanctum::actingAs($gerant);

        $this->postJson('/api/v1/clients', [
            'nom' => 'Comptoir Baobab SA',
            'contact' => 'M. Diallo',
            'canaux' => ['email' => 'ops@baobab.test', 'whatsapp' => '+2250700000000'],
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.nom', 'Comptoir Baobab SA')
            ->assertJsonPath('data.est_self', false)
            ->assertJsonPath('data.canaux.email', 'ops@baobab.test');
    }

    public function test_email_de_canal_invalide_rejete_422(): void
    {
        [, $gerant] = $this->tenantGerant();
        Sanctum::actingAs($gerant);

        $this->postJson('/api/v1/clients', [
            'nom' => 'X',
            'canaux' => ['email' => 'pas-un-email'],
        ])->assertStatus(422)->assertJsonValidationErrors('canaux.email');
    }

    public function test_liste_filtrable_par_nom(): void
    {
        [$tenant, $gerant] = $this->tenantGerant();
        $this->pourTenant($tenant, function (): void {
            Client::factory()->create(['nom' => 'Sahel Import']);
            Client::factory()->create(['nom' => 'Baobab Export']);
        });
        Sanctum::actingAs($gerant);

        $this->getJson('/api/v1/clients')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/clients?filter[nom]=baobab')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Baobab Export');
    }

    public function test_mise_a_jour_client(): void
    {
        [$tenant, $gerant] = $this->tenantGerant();
        $client = $this->pourTenant($tenant, fn (): Client => Client::factory()->create(['nom' => 'Ancien']));
        Sanctum::actingAs($gerant);

        $this->patchJson("/api/v1/clients/{$client->id}", ['nom' => 'Nouveau nom'])
            ->assertOk()
            ->assertJsonPath('data.nom', 'Nouveau nom');
    }

    public function test_isolation_tenant_sur_le_detail(): void
    {
        [$tenantA, $gerantA] = $this->tenantGerant();
        [$tenantB] = $this->tenantGerant();
        $clientB = $this->pourTenant($tenantB, fn (): Client => Client::factory()->create());

        Sanctum::actingAs($gerantA);
        // Le client d'un autre tenant est invisible (404, pas 403).
        $this->getJson("/api/v1/clients/{$clientB->id}")->assertNotFound();
    }

    public function test_le_comptable_lit_mais_ne_cree_pas(): void
    {
        [$tenant, ] = $this->tenantGerant();
        $comptable = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Comptable)->create();
        Sanctum::actingAs($comptable);

        $this->getJson('/api/v1/clients')->assertOk();
        $this->postJson('/api/v1/clients', ['nom' => 'X'])->assertForbidden();
    }
}
