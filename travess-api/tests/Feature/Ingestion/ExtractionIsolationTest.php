<?php

declare(strict_types=1);

namespace Tests\Feature\Ingestion;

use App\Domains\Documents\Enums\StatutExtraction;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\ExtractionIa;
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
 * Isolation multi-tenant des endpoints d'ingestion (principe n°3) : un acteur
 * d'un tenant ne peut ni lancer, ni consulter, ni valider l'extraction d'un
 * document d'un autre tenant. La RLS masque la ligne au route-model binding →
 * 404 (jamais 403, convention API du projet), aucune écriture chez l'autre.
 */
final class ExtractionIsolationTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * Crée un document (et une extraction réussie) dans le tenant B, puis
     * authentifie un gérant du tenant A.
     *
     * @return array{Tenant, string, string}
     */
    private function documentChezBpuisActeurA(): array
    {
        $tenantB = Tenant::factory()->create(['parametres' => ['ia_activee' => true]]);

        [$documentId, $extractionId] = $this->pourTenant($tenantB, function (): array {
            $client = Client::factory()->create();
            $dossier = Dossier::factory()->create(['client_id' => $client->id]);
            $document = Document::factory()->create(['dossier_id' => $dossier->id]);
            $extraction = ExtractionIa::factory()->create([
                'document_id' => $document->id,
                'statut' => StatutExtraction::Reussi->value,
                'valide_at' => null,
            ]);

            return [$document->id, $extraction->id];
        });

        $tenantA = Tenant::factory()->create(['parametres' => ['ia_activee' => true]]);
        Sanctum::actingAs(User::factory()->pourTenant($tenantA)->role(RoleUtilisateur::Gerant)->create());

        return [$tenantB, $documentId, $extractionId];
    }

    public function test_lancer_extraction_document_autre_tenant_404(): void
    {
        [$tenantB, $documentId] = $this->documentChezBpuisActeurA();

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(404);

        // Aucune extraction supplémentaire créée chez B (celle du seed exceptée).
        $this->pourTenant($tenantB, fn () => $this->assertSame(1, ExtractionIa::count()));
    }

    public function test_consulter_derniere_extraction_autre_tenant_404(): void
    {
        [, $documentId] = $this->documentChezBpuisActeurA();

        $this->getJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(404);
    }

    public function test_valider_extraction_autre_tenant_404_sans_ecriture(): void
    {
        [$tenantB, , $extractionId] = $this->documentChezBpuisActeurA();

        $this->postJson("/api/v1/extractions/{$extractionId}/validation", ['corrections' => []])
            ->assertStatus(404);

        // L'extraction de B reste non validée.
        $this->pourTenant($tenantB, fn () => $this->assertNull(ExtractionIa::findOrFail($extractionId)->valide_at));
    }
}
