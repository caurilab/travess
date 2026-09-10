<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Conteneurs\Enums\SourceSuiviTracking;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tracking\Adapters\AdaptateurTrackingFactice;
use App\Domains\Tracking\Jobs\PollerConteneur;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Surface HTTP du tracking (docs/08–09) : rafraîchissement à la demande mis en
 * file (202), refus quand le quota mutualisé est atteint (429), lecture du
 * dernier suivi, et étanchéité inter-tenant (un conteneur d'un autre tenant est
 * introuvable → 404).
 */
final class TrackingApiTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('tracking.cache_stats_ttl', 0);
    }

    /**
     * @return array{Tenant, User, Conteneur}
     */
    private function contexte(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        $conteneur = $this->pourTenant($tenant, function (): Conteneur {
            $bl = Bl::factory()->create();

            return Conteneur::factory()->create(['bl_id' => $bl->id]);
        });

        Sanctum::actingAs($gerant);

        return [$tenant, $gerant, $conteneur];
    }

    public function test_rafraichir_met_en_file_et_repond_202(): void
    {
        Bus::fake();
        [$tenant, , $conteneur] = $this->contexte();

        $this->postJson("/api/v1/conteneurs/{$conteneur->id}/tracking/rafraichir")
            ->assertStatus(202)
            ->assertJsonPath('data.statut', 'en_file');

        // Poll à la demande (force = true) mis en file pour le bon conteneur/tenant.
        Bus::assertDispatched(PollerConteneur::class, fn (PollerConteneur $job): bool => $job->conteneurId === $conteneur->id
            && $job->tenantId === $tenant->id
            && $job->force === true);
    }

    public function test_rafraichir_refuse_429_si_quota_atteint(): void
    {
        Bus::fake();
        [, , $conteneur] = $this->contexte();

        app(AdaptateurTrackingFactice::class)->poserQuotaConsomme(0.95);

        $this->postJson("/api/v1/conteneurs/{$conteneur->id}/tracking/rafraichir")
            ->assertStatus(429);

        // Rien n'est mis en file quand la garde coupe.
        Bus::assertNotDispatched(PollerConteneur::class);
    }

    public function test_lecture_renvoie_le_dernier_suivi(): void
    {
        [$tenant, , $conteneur] = $this->contexte();

        $this->pourTenant($tenant, function () use ($conteneur): void {
            SuiviTracking::factory()->create([
                'conteneur_id' => $conteneur->id,
                'source' => SourceSuiviTracking::Jsoncargo->value,
                'captured_at' => CarbonImmutable::now()->subDay(),
            ]);
            SuiviTracking::factory()->create([
                'conteneur_id' => $conteneur->id,
                'source' => SourceSuiviTracking::Imap->value,
                'emplacement' => 'Port de Lomé',
                'captured_at' => CarbonImmutable::now(),
            ]);
        });

        $this->getJson("/api/v1/conteneurs/{$conteneur->id}/tracking")
            ->assertOk()
            ->assertJsonPath('data.source', SourceSuiviTracking::Imap->value)
            ->assertJsonPath('data.emplacement', 'Port de Lomé')
            ->assertJsonPath('data.conteneur_id', $conteneur->id);
    }

    public function test_lecture_renvoie_null_sans_suivi(): void
    {
        [, , $conteneur] = $this->contexte();

        $this->getJson("/api/v1/conteneurs/{$conteneur->id}/tracking")
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }

    public function test_isolation_tenant_un_conteneur_d_un_autre_tenant_est_404(): void
    {
        Bus::fake();
        [, , $conteneurA] = $this->contexte();

        // Conteneur appartenant à un AUTRE tenant, invisible pour l'acteur courant.
        $autre = Tenant::factory()->create();
        $conteneurB = $this->pourTenant($autre, function (): Conteneur {
            $bl = Bl::factory()->create();

            return Conteneur::factory()->create(['bl_id' => $bl->id]);
        });

        // L'acteur reste le gérant du tenant A (contexte() a fait le actingAs).
        $this->getJson("/api/v1/conteneurs/{$conteneurB->id}/tracking")->assertStatus(404);
        $this->postJson("/api/v1/conteneurs/{$conteneurB->id}/tracking/rafraichir")->assertStatus(404);

        // Le conteneur du tenant A reste, lui, accessible.
        $this->getJson("/api/v1/conteneurs/{$conteneurA->id}/tracking")->assertOk();

        Bus::assertNotDispatched(PollerConteneur::class);
    }

    public function test_role_lecture_seule_ne_peut_pas_rafraichir(): void
    {
        Bus::fake();
        [$tenant, , $conteneur] = $this->contexte();

        // Le comptable a la lecture mais pas l'écriture (policy update).
        Sanctum::actingAs(User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Comptable)->create());

        $this->postJson("/api/v1/conteneurs/{$conteneur->id}/tracking/rafraichir")->assertStatus(403);
        $this->getJson("/api/v1/conteneurs/{$conteneur->id}/tracking")->assertOk();

        Bus::assertNotDispatched(PollerConteneur::class);
    }
}
