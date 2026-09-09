<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Models\Etape;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Models\AccesDossier;
use App\Domains\Portail\Services\LectureDossiersPartages;
use App\Domains\Portail\Services\MigrerProprieteDossier;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use App\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Moteur de migration de propriété (ADR-013, 7.3b1), testé « à blanc » (appel
 * direct du service, hors endpoint). Prouve le re-tenant complet de l'agrégat,
 * le remap d'armateur, la bascule de posture, l'octroi limite au client, et la
 * cohérence des FK composites (validées via SET CONSTRAINTS ALL IMMEDIATE).
 */
final class MigrationProprieteTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $workspace;

    private User $client;

    private Tenant $transitaire;

    private string $dossierId;

    private string $conteneurId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = Tenant::factory()->client()->create();
        $this->client = User::factory()->pourTenant($this->workspace)->role(RoleUtilisateur::Client)->create();

        // Dossier autonome complet (dossier + étape + BL + conteneur + parcours)
        // dans le workspace du client.
        $this->pourTenant($this->workspace, function (): void {
            $fiche = Client::factory()->create(['est_self' => true, 'nom' => 'Awa Traoré']);
            $dossier = Dossier::factory()->create(['client_id' => $fiche->id, 'posture' => PostureDossier::Autonome->value]);
            Etape::factory()->create(['dossier_id' => $dossier->id]);
            $armateur = Armateur::factory()->create(['nom' => 'Maersk']);
            $bl = Bl::factory()->create(['dossier_id' => $dossier->id, 'armateur_id' => $armateur->id]);
            $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id]);
            SuiviTracking::factory()->create(['conteneur_id' => $conteneur->id]);

            $this->dossierId = $dossier->id;
            $this->conteneurId = $conteneur->id;
        });

        $this->transitaire = Tenant::factory()->create();
    }

    private function migrer(): array
    {
        $dossier = $this->pourTenant($this->workspace, fn (): Dossier => Dossier::findOrFail($this->dossierId));

        return DB::transaction(fn (): array => app(MigrerProprieteDossier::class)
            ->executer($dossier, $this->workspace, $this->transitaire));
    }

    public function test_migration_re_tenante_tout_l_agregat(): void
    {
        $resultat = $this->migrer();
        $this->assertNotSame($resultat['ancienne_reference'], $resultat['nouvelle_reference']);

        // Chez le transitaire : dossier + agrégat présents, posture basculée.
        $this->pourTenant($this->transitaire, function (): void {
            $dossier = Dossier::findOrFail($this->dossierId);
            $this->assertSame(PostureDossier::GereParTransitaire, $dossier->posture);
            $this->assertSame($this->transitaire->id, $dossier->tenant_id);

            $this->assertSame(1, Bl::where('dossier_id', $this->dossierId)->count());
            $this->assertSame(1, Conteneur::where('bl_id', Bl::where('dossier_id', $this->dossierId)->value('id'))->count());
            $this->assertSame(1, SuiviTracking::where('conteneur_id', $this->conteneurId)->count());
            $this->assertSame(1, Etape::where('dossier_id', $this->dossierId)->count());

            // L'armateur du BL est bien une fiche du tenant transitaire.
            $armateurId = Bl::where('dossier_id', $this->dossierId)->value('armateur_id');
            $this->assertNotNull(Armateur::find($armateurId));
        });

        // Côté client : plus rien de l'agrégat n'est visible dans son tenant.
        $this->pourTenant($this->workspace, function (): void {
            $this->assertSame(0, Dossier::withoutGlobalScope(TenantScope::class)->where('id', $this->dossierId)->count());
            $this->assertSame(0, Bl::withoutGlobalScope(TenantScope::class)->where('dossier_id', $this->dossierId)->count());
        });
    }

    public function test_migration_cree_un_octroi_limite_pour_le_client(): void
    {
        $this->migrer();

        $this->pourTenant($this->transitaire, function (): void {
            $acces = AccesDossier::where('dossier_id', $this->dossierId)->firstOrFail();
            $this->assertSame(NiveauAcces::Limite, $acces->niveau);
            $this->assertSame(StatutAcces::Actif, $acces->statut);
            $this->assertSame($this->client->id, $acces->beneficiaire_user_id);
            $this->assertSame($this->workspace->id, $acces->beneficiaire_tenant_id);
        });
    }

    public function test_l_agregat_couvre_toutes_les_tables_rattachees_au_dossier(): void
    {
        // Anti-orphelin (audit 7.3b B1) : toute table portant tenant_id ET
        // référençant un membre de l'agrégat doit être soit migrée (ENFANTS/
        // spéciales), soit explicitement gardée (refus de migration). Une future
        // table non classée fait échouer ce test — jamais de fuite silencieuse.
        // On ne considère que les FK COMPOSITES (…, tenant_id) → parent(id,
        // tenant_id) : ce sont les enfants tenant-scopés qui doivent migrer. Les
        // tables inter-tenant (acces_dossier, demande_assignation,
        // invitation_portail) référencent dossiers.id par une FK SIMPLE et ne
        // font pas partie de l'agrégat migrable.
        $rattachees = collect(DB::select(<<<'SQL'
            SELECT DISTINCT c.conrelid::regclass::text AS tbl
            FROM pg_constraint c
            WHERE c.contype = 'f'
              AND c.confrelid::regclass::text IN ('dossiers', 'bls', 'conteneurs', 'documents')
              AND array_length(c.confkey, 1) = 2
        SQL))->pluck('tbl')->all();

        $classees = array_merge(
            MigrerProprieteDossier::classification()['enfants'],
            MigrerProprieteDossier::classification()['speciales'],
            MigrerProprieteDossier::classification()['gardees'],
        );

        $nonClassees = array_diff($rattachees, $classees);

        $this->assertSame([], array_values($nonClassees), 'Tables rattachées au dossier non classées : '.implode(', ', $nonClassees));
    }

    public function test_migration_refusee_si_paiement_attache(): void
    {
        // Une ligne « gardée » (paiement) attachée → refus (jamais d'orphelin).
        $this->pourTenant($this->workspace, function (): void {
            DB::table('paiements')->insert([
                'id' => (string) Str::uuid(),
                'tenant_id' => $this->workspace->id,
                'dossier_id' => $this->dossierId,
                'client_id' => Client::where('est_self', true)->value('id'),
                'montant' => 1000,
                'cible' => 'charge',
                'operateur' => 'orange',
                'statut' => 'initie',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->expectException(HttpException::class);
        $this->migrer();
    }

    public function test_apres_migration_le_client_voit_en_limite_via_le_portail(): void
    {
        $this->migrer();

        // Contexte portail du client : il voit le dossier partagé (BL + parcours).
        $this->pourTenant(
            $this->workspace,
            fn () => app(TenantContext::class)->sousPortail($this->client->id, function (): void {
                $dossiers = app(LectureDossiersPartages::class)->accessibles();
                $this->assertCount(1, $dossiers);
                $this->assertSame($this->dossierId, $dossiers->first()->id);
            }),
        );
    }
}
