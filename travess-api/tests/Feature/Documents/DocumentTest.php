<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

final class DocumentTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    /**
     * @return array{Tenant, User, string}
     */
    private function contexteAvecDossier(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        $client = $this->pourTenant($tenant, fn (): Client => Client::factory()->create());

        Sanctum::actingAs($gerant);
        $dossierId = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        return [$tenant, $gerant, $dossierId];
    }

    public function test_depot_stocke_le_fichier_et_ses_metadonnees(): void
    {
        [, , $dossierId] = $this->contexteAvecDossier();

        $reponse = $this->post('/api/v1/documents', [
            'dossier_id' => $dossierId,
            'type' => 'bl',
            'fichier' => UploadedFile::fake()->create('connaissement.pdf', 120, 'application/pdf'),
        ]);

        $reponse->assertStatus(201)
            ->assertJsonPath('data.type', 'bl')
            ->assertJsonPath('data.nom_original', 'connaissement.pdf')
            ->assertJsonPath('data.statut_ingestion', 'none');

        $this->assertNotNull($reponse->json('data.taille'));
    }

    public function test_rejet_d_un_type_de_fichier_non_autorise(): void
    {
        [, , $dossierId] = $this->contexteAvecDossier();

        $this->post('/api/v1/documents', [
            'dossier_id' => $dossierId,
            'type' => 'autre',
            'fichier' => UploadedFile::fake()->create('script.exe', 10, 'application/x-msdownload'),
        ])->assertStatus(422)->assertJsonValidationErrorFor('fichier');
    }

    public function test_consultation_des_metadonnees(): void
    {
        [, , $dossierId] = $this->contexteAvecDossier();
        $id = $this->post('/api/v1/documents', [
            'dossier_id' => $dossierId,
            'type' => 'facture_charges',
            'fichier' => UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf'),
        ])->json('data.id');

        $this->getJson("/api/v1/documents/{$id}")
            ->assertOk()
            ->assertJsonPath('data.type', 'facture_charges');
    }

    public function test_un_document_d_un_autre_tenant_est_introuvable(): void
    {
        [, , $dossierId] = $this->contexteAvecDossier();
        $idB = $this->post('/api/v1/documents', [
            'dossier_id' => $dossierId,
            'type' => 'bl',
            'fichier' => UploadedFile::fake()->create('bl.pdf', 30, 'application/pdf'),
        ])->json('data.id');

        $tenantA = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($tenantA)->role(RoleUtilisateur::Gerant)->create());

        $this->getJson("/api/v1/documents/{$idB}")->assertNotFound();
    }
}
