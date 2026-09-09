<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Finances\Models\Charge;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Models\AccesDossier;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Vue limitée du portail client (ADR-013, sous-lot 7.1) : le client voit le
 * dossier partagé projeté en liste blanche (BL + parcours), jamais les finances
 * ni les informations internes, et rien sur un dossier non octroyé.
 */
final class PortailDossierTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $transitaire;

    private Tenant $workspaceClient;

    private User $compteClient;

    private string $dossierPartageId;

    private string $dossierNonPartageId;

    private string $referencePartage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transitaire = Tenant::factory()->create();
        $this->pourTenant($this->transitaire, function (): void {
            $client = Client::factory()->create();

            $dossier = Dossier::factory()->create(['client_id' => $client->id, 'motif_blocage' => 'attente paiement client']);
            $bl = Bl::factory()->create(['dossier_id' => $dossier->id, 'numero' => 'BL-PARTAGE', 'armateur_id' => Armateur::factory()->create()->id]);
            $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id, 'numero' => 'MSCU7390252']);
            SuiviTracking::factory()->create(['conteneur_id' => $conteneur->id, 'emplacement' => 'Port de Lomé']);
            Charge::factory()->create(['dossier_id' => $dossier->id, 'libelle' => 'Droits de douane']);
            $this->dossierPartageId = $dossier->id;
            $this->referencePartage = $dossier->reference;

            // Un second dossier NON partagé au client.
            $this->dossierNonPartageId = Dossier::factory()->create(['client_id' => $client->id])->id;
        });

        $this->workspaceClient = Tenant::factory()->client()->create();
        $this->compteClient = User::factory()->pourTenant($this->workspaceClient)->role(RoleUtilisateur::Client)->create();
    }

    private function octroyer(StatutAcces $statut = StatutAcces::Actif): void
    {
        $this->pourTenant($this->transitaire, fn (): AccesDossier => AccesDossier::factory()->create([
            'dossier_id' => $this->dossierPartageId,
            'tenant_proprietaire_id' => $this->transitaire->id,
            'beneficiaire_user_id' => $this->compteClient->id,
            'beneficiaire_tenant_id' => $this->workspaceClient->id,
            'statut' => $statut->value,
            'origine' => OrigineAcces::InvitationTransitaire->value,
        ]));
    }

    public function test_le_client_voit_le_dossier_partage_projete(): void
    {
        $this->octroyer();
        Sanctum::actingAs($this->compteClient);

        $reponse = $this->getJson("/api/v1/portail/dossiers/{$this->dossierPartageId}");

        $reponse->assertOk()
            ->assertJsonPath('data.reference', $this->referencePartage)
            ->assertJsonPath('data.bls.0.numero', 'BL-PARTAGE')
            ->assertJsonPath('data.bls.0.conteneurs.0.numero', 'MSCU7390252')
            ->assertJsonPath('data.bls.0.conteneurs.0.parcours.emplacement', 'Port de Lomé');
    }

    public function test_la_projection_n_expose_ni_finances_ni_infos_internes(): void
    {
        $this->octroyer();
        Sanctum::actingAs($this->compteClient);

        $contenu = $this->getJson("/api/v1/portail/dossiers/{$this->dossierPartageId}")->getContent();

        $this->assertStringNotContainsString('motif_blocage', $contenu);
        $this->assertStringNotContainsString('attente paiement', $contenu);
        $this->assertStringNotContainsString('Droits de douane', $contenu); // finances
        $this->assertStringNotContainsString('snapshot', $contenu); // tracking brut
    }

    public function test_index_ne_liste_que_les_dossiers_octroyes(): void
    {
        $this->octroyer();
        Sanctum::actingAs($this->compteClient);

        $this->getJson('/api/v1/portail/dossiers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->dossierPartageId);
    }

    public function test_dossier_non_octroye_est_introuvable(): void
    {
        $this->octroyer();
        Sanctum::actingAs($this->compteClient);

        $this->getJson("/api/v1/portail/dossiers/{$this->dossierNonPartageId}")->assertNotFound();
    }

    public function test_sans_octroi_actif_rien_n_est_visible(): void
    {
        $this->octroyer(StatutAcces::EnAttente);
        Sanctum::actingAs($this->compteClient);

        $this->getJson('/api/v1/portail/dossiers')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/portail/dossiers/{$this->dossierPartageId}")->assertNotFound();
    }

    public function test_un_beneficiaire_fourni_en_entree_est_refuse(): void
    {
        $this->octroyer();
        Sanctum::actingAs($this->compteClient);

        // Le bénéficiaire ne se fournit jamais côté client (discipline principe n°3).
        $this->getJson('/api/v1/portail/dossiers?portail_user_id='.$this->compteClient->id)
            ->assertStatus(422);
    }
}
