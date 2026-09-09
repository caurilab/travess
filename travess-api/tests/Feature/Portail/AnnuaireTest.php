<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Annuaire opt-in des transitaires (ADR-013, 7.4) : seuls les transitaires
 * ayant activé leur visibilité apparaissent et sont assignables.
 */
final class AnnuaireTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $workspace;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workspace = Tenant::factory()->client()->create();
        $this->client = User::factory()->pourTenant($this->workspace)->role(RoleUtilisateur::Client)->create();
    }

    public function test_annuaire_ne_liste_que_les_transitaires_opt_in(): void
    {
        Tenant::factory()->create(['nom' => 'Transit Public', 'annuaire_public' => true]);
        Tenant::factory()->create(['nom' => 'Transit Prive', 'annuaire_public' => false]);

        Sanctum::actingAs($this->client);

        $this->getJson('/api/v1/portail/autonome/annuaire')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nom', 'Transit Public');
    }

    public function test_un_transitaire_gerant_active_sa_visibilite(): void
    {
        $transitaire = Tenant::factory()->create(['annuaire_public' => false]);
        Sanctum::actingAs(User::factory()->pourTenant($transitaire)->role(RoleUtilisateur::Gerant)->create());

        $this->putJson('/api/v1/agence/annuaire', ['public' => true])
            ->assertOk()
            ->assertJsonPath('annuaire_public', true);

        $this->assertTrue($transitaire->fresh()->annuaire_public);
    }

    public function test_un_agent_non_gerant_ne_peut_pas_changer_la_visibilite(): void
    {
        $transitaire = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($transitaire)->role(RoleUtilisateur::Agent)->create());

        $this->putJson('/api/v1/agence/annuaire', ['public' => true])->assertStatus(403);
    }

    public function test_assignation_refusee_vers_un_transitaire_hors_annuaire(): void
    {
        $horsAnnuaire = Tenant::factory()->create(['annuaire_public' => false]);

        $dossierId = $this->pourTenant($this->workspace, function (): string {
            $fiche = Client::factory()->create(['est_self' => true]);

            return Dossier::factory()->create(['client_id' => $fiche->id, 'posture' => PostureDossier::Autonome->value])->id;
        });

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/portail/autonome/dossiers/{$dossierId}/assignation", [
            'transitaire_id' => $horsAnnuaire->id,
        ])->assertStatus(422);
    }
}
