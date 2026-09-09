<?php

declare(strict_types=1);

namespace Tests\Feature\Ingestion;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Domains\Consommation\Models\ConsommationService;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Documents\Enums\StatutExtraction;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\ExtractionIa;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Ingestion\Adapters\ExtracteurFactice;
use App\Domains\Ingestion\Contracts\ExtracteurDocument;
use App\Domains\Ingestion\Data\ChampExtrait;
use App\Domains\Ingestion\Services\DecompteConsommationIa;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Pipeline d'ingestion IA de bout en bout, SANS clé API : l'extracteur factice
 * (déterministe) remplace le fournisseur réel. Couvre l'opt-in, le plafond de
 * quota, l'idempotence, la mise en file, le décompte, l'échec et la validation
 * humaine (l'IA propose, l'humain valide).
 */
final class ExtractionPipelineTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    /**
     * @param  array<string, mixed>  $parametres
     * @return array{Tenant, User, string}
     */
    private function contexte(array $parametres = ['ia_activee' => true], int $quota = 100): array
    {
        $tenant = Tenant::factory()->create(['parametres' => $parametres, 'quota_ia_mensuel' => $quota]);
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();
        $client = $this->pourTenant($tenant, fn (): Client => Client::factory()->create());

        Sanctum::actingAs($gerant);
        $dossierId = $this->postJson('/api/v1/dossiers', ['sens' => 'import', 'client_id' => $client->id])->json('data.id');

        return [$tenant, $gerant, $dossierId];
    }

    private function deposerBl(string $dossierId): string
    {
        return $this->post('/api/v1/documents', [
            'dossier_id' => $dossierId,
            'type' => 'bl',
            'fichier' => UploadedFile::fake()->create('bl.pdf', 60, 'application/pdf'),
        ])->json('data.id');
    }

    public function test_extraction_refusee_si_ia_non_activee(): void
    {
        [, , $dossierId] = $this->contexte(parametres: []);
        $documentId = $this->deposerBl($dossierId);

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(403);

        $this->assertDatabaseCount('extractions_ia', 0);
    }

    public function test_extraction_refusee_si_quota_atteint(): void
    {
        [$tenant, , $dossierId] = $this->contexte(quota: 1);
        $documentId = $this->deposerBl($dossierId);

        // Consommation déjà au plafond.
        $this->pourTenant($tenant, fn () => ConsommationService::factory()->create([
            'service' => ServiceConsomme::Ia->value,
            'periode' => now()->format('Y-m'),
            'quantite' => 1,
        ]));

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(429);
    }

    public function test_pipeline_complet_avec_extracteur_factice(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->postJson("/api/v1/documents/{$documentId}/extraction")
            ->assertStatus(202)
            ->assertJsonPath('data.statut', StatutExtraction::EnFile->value);

        // Queue sync (afterCommit) : le job s'est exécuté → extraction réussie.
        $this->pourTenant($tenant, function () use ($documentId): void {
            $extraction = ExtractionIa::first();
            $this->assertSame(StatutExtraction::Reussi, $extraction->statut);
            $this->assertArrayHasKey('numero_bl', $extraction->champs);
            $this->assertSame('extrait', Document::find($documentId)->statut_ingestion->value);

            // Décompte mensuel incrémenté.
            $conso = ConsommationService::query()->where('service', ServiceConsomme::Ia->value)->sum('quantite');
            $this->assertSame(1, (int) $conso);
        });
    }

    public function test_idempotence_pas_de_relance_si_extraction_en_file(): void
    {
        Queue::fake();

        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        // Une extraction déjà en file : une seconde demande la renvoie telle quelle.
        $enFile = $this->pourTenant($tenant, fn (): ExtractionIa => ExtractionIa::factory()->create([
            'document_id' => $documentId,
            'statut' => StatutExtraction::EnFile->value,
        ]));

        $this->postJson("/api/v1/documents/{$documentId}/extraction")
            ->assertStatus(202)
            ->assertJsonPath('data.id', $enFile->id);

        // Compte via Eloquent sous contexte : la RLS masque les lignes hors tenant.
        $this->pourTenant($tenant, fn () => $this->assertSame(1, ExtractionIa::count()));
        Queue::assertNothingPushed();
    }

    public function test_echec_extraction_marque_le_document_relancable(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->app->instance(ExtracteurDocument::class, (new ExtracteurFactice)->echouera());

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);

        $this->pourTenant($tenant, function () use ($documentId): void {
            $this->assertSame(StatutExtraction::Echoue, ExtractionIa::first()->statut);
            $this->assertSame('none', Document::find($documentId)->statut_ingestion->value);
        });
    }

    public function test_validation_humaine_cree_le_bl_et_ses_conteneurs(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        // Le factice propose un numéro ; l'armateur_id est fourni à la validation.
        $this->app->instance(ExtracteurDocument::class, (new ExtracteurFactice)->simuler([
            'numero_bl' => new ChampExtrait('MAEU6123458BL', 0.88, 'p.1'),
            'navire_nom' => new ChampExtrait('EVER GIVEN', 0.7, 'p.1'),
        ]));

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);
        $extraction = $this->pourTenant($tenant, fn (): ExtractionIa => ExtractionIa::firstOrFail());

        $armateur = $this->pourTenant($tenant, fn (): Armateur => Armateur::factory()->create());

        $reponse = $this->postJson("/api/v1/extractions/{$extraction->id}/validation", [
            'corrections' => [
                'numero_bl' => 'MAEU-2026-001',
                'armateur_id' => $armateur->id,
                'conteneurs' => [
                    ['numero' => 'MSCU7390252', 'type' => '40'],
                ],
            ],
        ]);

        $reponse->assertOk()->assertJsonPath('extraction.statut', StatutExtraction::Reussi->value);

        $this->assertNotNull($reponse->json('extraction.valide_at'));

        $this->pourTenant($tenant, function () use ($dossierId, $documentId): void {
            $this->assertSame('valide', Document::findOrFail($documentId)->statut_ingestion->value);

            $bl = Bl::where('dossier_id', $dossierId)->first();
            $this->assertNotNull($bl);
            $this->assertSame('MAEU-2026-001', $bl->numero);
            $this->assertSame(1, Conteneur::where('bl_id', $bl->id)->count());
        });
    }

    public function test_validation_impossible_sur_extraction_non_reussie(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $extraction = $this->pourTenant($tenant, fn (): ExtractionIa => ExtractionIa::factory()->create([
            'document_id' => $documentId,
            'statut' => StatutExtraction::EnFile->value,
        ]));

        $this->postJson("/api/v1/extractions/{$extraction->id}/validation", ['corrections' => []])
            ->assertStatus(409);
    }

    public function test_quota_est_un_plafond_dur_des_le_lancement(): void
    {
        // Deux documents, quota = 1 : la réservation atomique au lancement empêche
        // le second de passer, même avant que le premier job n'ait « facturé ».
        [, , $dossierId] = $this->contexte(quota: 1);
        $docA = $this->deposerBl($dossierId);
        $docB = $this->deposerBl($dossierId);

        $this->postJson("/api/v1/documents/{$docA}/extraction")->assertStatus(202);
        $this->postJson("/api/v1/documents/{$docB}/extraction")->assertStatus(429);
    }

    public function test_double_validation_refusee_sans_double_application(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->app->instance(ExtracteurDocument::class, (new ExtracteurFactice)->simuler([
            'numero_bl' => new ChampExtrait('MAEU6123458BL', 0.9, 'p.1'),
        ]));
        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);
        $extraction = $this->pourTenant($tenant, fn (): ExtractionIa => ExtractionIa::firstOrFail());
        $armateur = $this->pourTenant($tenant, fn (): Armateur => Armateur::factory()->create());

        $corrections = ['corrections' => ['numero_bl' => 'BL-1', 'armateur_id' => $armateur->id]];

        $this->postJson("/api/v1/extractions/{$extraction->id}/validation", $corrections)->assertOk();
        $this->postJson("/api/v1/extractions/{$extraction->id}/validation", $corrections)->assertStatus(409);

        // Un seul BL malgré la seconde tentative.
        $this->pourTenant($tenant, fn () => $this->assertSame(1, Bl::where('dossier_id', $dossierId)->count()));
    }

    public function test_application_refusee_si_armateur_manquant(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);
        $extraction = $this->pourTenant($tenant, fn (): ExtractionIa => ExtractionIa::firstOrFail());

        // numero_bl fourni, armateur_id absent → 422, aucun BL, extraction non validée.
        $this->postJson("/api/v1/extractions/{$extraction->id}/validation", [
            'corrections' => ['numero_bl' => 'BL-1'],
        ])->assertStatus(422);

        $this->pourTenant($tenant, function () use ($dossierId, $extraction): void {
            $this->assertSame(0, Bl::where('dossier_id', $dossierId)->count());
            $this->assertNull(ExtractionIa::findOrFail($extraction->id)->valide_at);
        });
    }

    public function test_conteneur_iso_invalide_ignore_le_bl_est_cree(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);
        $extraction = $this->pourTenant($tenant, fn (): ExtractionIa => ExtractionIa::firstOrFail());
        $armateur = $this->pourTenant($tenant, fn (): Armateur => Armateur::factory()->create());

        $this->postJson("/api/v1/extractions/{$extraction->id}/validation", [
            'corrections' => [
                'numero_bl' => 'BL-1',
                'armateur_id' => $armateur->id,
                'conteneurs' => [
                    ['numero' => 'MSCU7390252', 'type' => '40'],   // valide
                    ['numero' => 'MSCU0000000', 'type' => '40'],   // clé de contrôle fausse
                    ['numero' => 'ABCU1234567'],                   // type manquant
                    'pas-un-objet',                                 // entrée scalaire
                ],
            ],
        ])->assertOk();

        $this->pourTenant($tenant, function () use ($dossierId): void {
            $bl = Bl::where('dossier_id', $dossierId)->firstOrFail();
            $this->assertSame(1, Conteneur::where('bl_id', $bl->id)->count());
        });
    }

    public function test_derniere_extraction_404_si_aucune(): void
    {
        [, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->getJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(404);
    }

    public function test_derniere_extraction_renvoie_la_plus_recente(): void
    {
        [, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);

        $this->getJson("/api/v1/documents/{$documentId}/extraction")
            ->assertOk()
            ->assertJsonPath('data.document_id', $documentId)
            ->assertJsonPath('data.statut', StatutExtraction::Reussi->value);
    }

    public function test_role_sans_ecriture_ne_peut_pas_lancer(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        // Un comptable (lecture seule sur documents) est refusé.
        Sanctum::actingAs(User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Comptable)->create());

        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(403);
        $this->pourTenant($tenant, fn () => $this->assertSame(0, ExtractionIa::count()));
    }

    public function test_echec_ne_decompte_pas_le_quota(): void
    {
        [$tenant, , $dossierId] = $this->contexte();
        $documentId = $this->deposerBl($dossierId);

        $this->app->instance(ExtracteurDocument::class, (new ExtracteurFactice)->echouera());
        $this->postJson("/api/v1/documents/{$documentId}/extraction")->assertStatus(202);

        // Réservé au lancement (1), puis libéré à l'échec → retour à 0.
        $this->pourTenant($tenant, fn () => $this->assertSame(0, app(DecompteConsommationIa::class)->quantiteDuMois()));
    }
}
