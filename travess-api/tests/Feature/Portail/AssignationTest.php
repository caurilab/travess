<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Portail\Enums\StatutDemande;
use App\Domains\Portail\Models\DemandeAssignation;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Assignation d'un transitaire par un client autonome (ADR-013, 7.3b2) :
 * demande (client) → inbox + acceptation (transitaire) → migration de propriété
 * → le client bascule en vue limitée. Plus refus et isolation.
 */
final class AssignationTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $workspace;

    private User $client;

    private Tenant $transitaire;

    private User $gerant;

    private string $dossierId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Tenant::factory()->client()->create();
        $this->client = User::factory()->pourTenant($this->workspace)->role(RoleUtilisateur::Client)->create();
        $this->pourTenant($this->workspace, function (): void {
            $fiche = Client::factory()->create(['est_self' => true, 'nom' => 'Awa Traoré']);
            $dossier = Dossier::factory()->create(['client_id' => $fiche->id, 'posture' => PostureDossier::Autonome->value]);
            $bl = Bl::factory()->create(['dossier_id' => $dossier->id, 'armateur_id' => Armateur::factory()->create(['nom' => 'Maersk'])->id]);
            Conteneur::factory()->create(['bl_id' => $bl->id]);
            $this->dossierId = $dossier->id;
        });

        $this->transitaire = Tenant::factory()->create(['annuaire_public' => true]);
        $this->gerant = User::factory()->pourTenant($this->transitaire)->role(RoleUtilisateur::Gerant)->create();
    }

    private function demander(): string
    {
        Sanctum::actingAs($this->client);
        $id = $this->postJson("/api/v1/portail/autonome/dossiers/{$this->dossierId}/assignation", [
            'transitaire_id' => $this->transitaire->id,
        ])->assertStatus(202)->json('data.id');
        $this->app['auth']->forgetGuards();

        return $id;
    }

    public function test_flux_demande_acceptation_migration(): void
    {
        $demandeId = $this->demander();

        // Le transitaire voit la demande dans son inbox et l'accepte.
        Sanctum::actingAs($this->gerant);
        $this->getJson('/api/v1/demandes-assignation')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/demandes-assignation/{$demandeId}/accepter")
            ->assertOk()
            ->assertJsonStructure(['ancienne_reference', 'nouvelle_reference']);

        // Dossier migré chez le transitaire, posture basculée, demande acceptée.
        $this->pourTenant($this->transitaire, function () use ($demandeId): void {
            $this->assertSame(PostureDossier::GereParTransitaire, Dossier::findOrFail($this->dossierId)->posture);
            $this->assertSame(StatutDemande::Acceptee, DemandeAssignation::findOrFail($demandeId)->statut);
        });

        // Le client bascule en vue limitée (BL + parcours) via le portail.
        Sanctum::actingAs($this->client);
        $this->getJson('/api/v1/portail/dossiers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->dossierId);

        // Et il n'a plus la main sur la surface autonome (posture ≠ autonome).
        $this->getJson("/api/v1/portail/autonome/dossiers/{$this->dossierId}")->assertNotFound();
    }

    public function test_refus_laisse_le_dossier_autonome(): void
    {
        $demandeId = $this->demander();

        Sanctum::actingAs($this->gerant);
        $this->postJson("/api/v1/demandes-assignation/{$demandeId}/refuser", ['motif' => 'hors zone'])
            ->assertOk()
            ->assertJsonPath('data.statut', StatutDemande::Refusee->value);

        $this->pourTenant($this->workspace, fn () => $this->assertSame(
            PostureDossier::Autonome,
            Dossier::withoutGlobalScope(TenantScope::class)->findOrFail($this->dossierId)->posture,
        ));
    }

    public function test_demande_idempotente(): void
    {
        $premier = $this->demander();
        $second = $this->demander();

        $this->assertSame($premier, $second);
        $this->pourTenant($this->transitaire, fn () => $this->assertSame(1, DemandeAssignation::count()));
    }

    public function test_un_autre_transitaire_ne_voit_ni_ne_decide_la_demande(): void
    {
        $demandeId = $this->demander();

        $autre = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($autre)->role(RoleUtilisateur::Gerant)->create());

        $this->getJson('/api/v1/demandes-assignation')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/demandes-assignation/{$demandeId}/accepter")->assertStatus(404);
    }

    public function test_un_agent_non_gerant_ne_peut_pas_accepter(): void
    {
        $demandeId = $this->demander();

        Sanctum::actingAs(User::factory()->pourTenant($this->transitaire)->role(RoleUtilisateur::Agent)->create());
        $this->postJson("/api/v1/demandes-assignation/{$demandeId}/accepter")->assertStatus(403);
    }
}
