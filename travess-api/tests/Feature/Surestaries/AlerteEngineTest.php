<?php

declare(strict_types=1);

namespace Tests\Feature\Surestaries;

use App\Domains\Alertes\Models\Alerte;
use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Surestaries\Jobs\RafraichirSurestaries;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

final class AlerteEngineTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // date_fin_franchise = 2026-01-07 (début 01-01 + 7 jours francs) → J0.
        Carbon::setTestNow('2026-01-07');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{Tenant, User, string}
     */
    private function contexteAvecFranchise(): array
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
        $this->postJson("/api/v1/conteneurs/{$conteneurId}/franchises", ['type' => 'surestaries', 'date_debut' => '2026-01-01', 'jours_francs' => 7]);

        return [$tenant, $gerant, $conteneurId];
    }

    public function test_le_job_genere_une_alerte_de_seuil_idempotente(): void
    {
        [$tenant, , $conteneurId] = $this->contexteAvecFranchise();

        RafraichirSurestaries::dispatch($tenant->id);
        RafraichirSurestaries::dispatch($tenant->id); // re-run → pas de doublon

        $this->pourTenant($tenant, function () use ($conteneurId): void {
            $alertes = Alerte::where('conteneur_id', $conteneurId)->get();
            $this->assertCount(1, $alertes);
            $this->assertSame('surestaries_j0', $alertes->first()?->type->value);
        });
    }

    public function test_les_alertes_sont_listees_et_marquables(): void
    {
        [$tenant, $gerant] = $this->contexteAvecFranchise();
        RafraichirSurestaries::dispatch($tenant->id);

        Sanctum::actingAs($gerant);
        $alerteId = $this->getJson('/api/v1/alertes')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');

        $this->patchJson("/api/v1/alertes/{$alerteId}", ['statut' => 'traitee'])
            ->assertOk()
            ->assertJsonPath('data.statut', 'traitee');
    }

    public function test_dashboard_argent_en_feu(): void
    {
        [$tenant, $gerant] = $this->contexteAvecFranchise();
        RafraichirSurestaries::dispatch($tenant->id);

        Sanctum::actingAs($gerant);
        $this->getJson('/api/v1/dashboard/argent-en-feu')
            ->assertOk()
            ->assertJsonPath('data.menacant_cumule', 45000)
            ->assertJsonCount(1, 'data.conteneurs_a_risque');
    }

    public function test_dashboard_surestaries_evitees(): void
    {
        [$tenant, $gerant, $conteneurId] = $this->contexteAvecFranchise();
        RafraichirSurestaries::dispatch($tenant->id); // génère l'alerte (menaçant 45000)

        // Le conteneur sort à temps (rendu) : la menace ne se réalise pas.
        Sanctum::actingAs($gerant);
        $this->patchJson("/api/v1/conteneurs/{$conteneurId}", ['statut' => 'rendu'])->assertOk();
        RafraichirSurestaries::dispatch($tenant->id); // fige la franchise (inactive)

        $this->getJson('/api/v1/dashboard/surestaries-evitees')
            ->assertOk()
            ->assertJsonPath('data.montant_evite', 45000)
            ->assertJsonPath('data.nombre_conteneurs', 1);
    }
}
