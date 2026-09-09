<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Isolation des utilisateurs (garde-fous E1). User n'étant pas auto-scopé, on
 * vérifie explicitement qu'aucun accès inter-tenant n'est possible via les
 * endpoints users.
 */
final class UserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_gerant_ne_voit_que_les_utilisateurs_de_son_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $gerantA = User::factory()->pourTenant($a)->role(RoleUtilisateur::Gerant)->create();
        User::factory()->pourTenant($a)->role(RoleUtilisateur::Agent)->create();
        User::factory()->pourTenant($b)->count(3)->create();

        Sanctum::actingAs($gerantA);

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(2, 'data'); // gérant + agent de A uniquement
    }

    public function test_acceder_a_un_utilisateur_d_un_autre_tenant_renvoie_404(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $gerantA = User::factory()->pourTenant($a)->role(RoleUtilisateur::Gerant)->create();
        $userB = User::factory()->pourTenant($b)->create();

        Sanctum::actingAs($gerantA);

        // 404 (pas 403) : on ne révèle pas l'existence de l'utilisateur de B.
        $this->getJson("/api/v1/users/{$userB->id}")->assertNotFound();
    }

    public function test_un_gerant_accede_a_un_utilisateur_de_son_tenant(): void
    {
        $a = Tenant::factory()->create();
        $gerantA = User::factory()->pourTenant($a)->role(RoleUtilisateur::Gerant)->create();
        $agentA = User::factory()->pourTenant($a)->role(RoleUtilisateur::Agent)->create();

        Sanctum::actingAs($gerantA);

        $this->getJson("/api/v1/users/{$agentA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $agentA->id);
    }

    public function test_un_agent_ne_peut_pas_lister_les_utilisateurs(): void
    {
        $a = Tenant::factory()->create();
        $agentA = User::factory()->pourTenant($a)->role(RoleUtilisateur::Agent)->create();

        Sanctum::actingAs($agentA);

        $this->getJson('/api/v1/users')->assertForbidden();
    }
}
