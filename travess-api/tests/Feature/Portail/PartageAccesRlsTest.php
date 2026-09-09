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
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Models\AccesDossier;
use App\Domains\Portail\Services\LectureDossiersPartages;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Étanchéité du partage inter-tenant (ADR-013, sous-lot 7.0), prouvée EN BASE.
 *
 * Un bénéficiaire (compte client) muni d'un accès « limite » actif voit le
 * dossier partagé et sa projection partageable (BL + conteneurs + parcours),
 * jamais le reste du tenant propriétaire (finances…), et rien du tout sans
 * octroi actif ni contexte portail. Le partage est en lecture seule.
 */
final class PartageAccesRlsTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $transitaire;

    private Tenant $workspaceClient;

    private User $compteClient;

    private string $dossierId;

    private string $conteneurId;

    private string $chargeId;

    protected function setUp(): void
    {
        parent::setUp();

        // Données métier chez le transitaire T.
        $this->transitaire = Tenant::factory()->create();
        $this->pourTenant($this->transitaire, function (): void {
            $client = Client::factory()->create();
            $dossier = Dossier::factory()->create(['client_id' => $client->id]);
            $bl = Bl::factory()->create(['dossier_id' => $dossier->id, 'armateur_id' => Armateur::factory()->create()->id]);
            $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id]);
            SuiviTracking::factory()->create(['conteneur_id' => $conteneur->id]);
            $charge = Charge::factory()->create(['dossier_id' => $dossier->id]);

            $this->dossierId = $dossier->id;
            $this->conteneurId = $conteneur->id;
            $this->chargeId = $charge->id;
        });

        // Workspace client C + compte client U.
        $this->workspaceClient = Tenant::factory()->client()->create();
        $this->compteClient = User::factory()->pourTenant($this->workspaceClient)->role(RoleUtilisateur::Client)->create();
    }

    private function octroyer(StatutAcces $statut = StatutAcces::Actif, NiveauAcces $niveau = NiveauAcces::Limite): AccesDossier
    {
        // L'octroi s'écrit dans le contexte du tenant propriétaire (RLS écriture).
        return $this->pourTenant($this->transitaire, fn (): AccesDossier => AccesDossier::factory()->create([
            'dossier_id' => $this->dossierId,
            'tenant_proprietaire_id' => $this->transitaire->id,
            'beneficiaire_user_id' => $this->compteClient->id,
            'beneficiaire_tenant_id' => $this->workspaceClient->id,
            'niveau' => $niveau->value,
            'statut' => $statut->value,
            'origine' => OrigineAcces::InvitationTransitaire->value,
        ]));
    }

    /**
     * Exécute le callback sous le contexte portail complet du compte client :
     * tenant courant = son workspace, bénéficiaire = lui-même.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    private function sousPortailClient(\Closure $callback): mixed
    {
        return $this->pourTenant(
            $this->workspaceClient,
            fn () => app(TenantContext::class)->sousPortail($this->compteClient->id, $callback),
        );
    }

    private function dossiersVisiblesSansScope(): int
    {
        return Dossier::query()->withoutGlobalScope(TenantScope::class)->count();
    }

    public function test_beneficiaire_actif_voit_le_dossier_partage_et_sa_projection(): void
    {
        $this->octroyer();

        $this->sousPortailClient(function (): void {
            $dossiers = app(LectureDossiersPartages::class)->accessibles();
            $this->assertCount(1, $dossiers);
            $this->assertSame($this->dossierId, $dossiers->first()->id);

            // Projection partageable visible (BL + conteneurs + parcours).
            $this->assertSame(1, Bl::query()->withoutGlobalScope(TenantScope::class)->count());
            $this->assertSame(1, Conteneur::query()->withoutGlobalScope(TenantScope::class)->count());
            $this->assertSame(1, SuiviTracking::query()->withoutGlobalScope(TenantScope::class)->count());
        });
    }

    public function test_beneficiaire_ne_voit_pas_les_donnees_hors_projection(): void
    {
        $this->octroyer();

        $this->sousPortailClient(function (): void {
            // Les finances du transitaire restent invisibles (aucune politique
            // de partage sur charges ; le workspace client n'en est pas propriétaire).
            $this->assertSame(0, Charge::query()->withoutGlobalScope(TenantScope::class)->count());
        });
    }

    public function test_sans_contexte_portail_rien_n_est_visible(): void
    {
        $this->octroyer();

        // Contexte tenant du client seul, SANS app.portail_user_id.
        $this->pourTenant($this->workspaceClient, function (): void {
            $this->assertSame(0, $this->dossiersVisiblesSansScope());
        });
    }

    public function test_octroi_en_attente_ne_donne_pas_acces(): void
    {
        $this->octroyer(StatutAcces::EnAttente);

        $this->sousPortailClient(function (): void {
            $this->assertSame(0, $this->dossiersVisiblesSansScope());
            $this->assertSame(0, Conteneur::query()->withoutGlobalScope(TenantScope::class)->count());
        });
    }

    public function test_octroi_revoque_ferme_l_acces(): void
    {
        $this->octroyer(StatutAcces::Revoque);

        $this->sousPortailClient(function (): void {
            $this->assertSame(0, $this->dossiersVisiblesSansScope());
        });
    }

    public function test_le_partage_est_en_lecture_seule(): void
    {
        $this->octroyer();

        $this->sousPortailClient(function (): void {
            $dossier = app(LectureDossiersPartages::class)->trouver($this->dossierId);
            $this->assertNotNull($dossier);

            // Aucune écriture inter-tenant : la politique de partage est FOR SELECT ;
            // l'UPDATE ne trouve aucune ligne modifiable (politique tenant = C, non
            // propriétaire) → 0 ligne affectée.
            $affectees = Dossier::query()
                ->withoutGlobalScope(TenantScope::class)
                ->whereKey($this->dossierId)
                ->update(['statut' => 'clos']);

            $this->assertSame(0, $affectees);
        });
    }

    public function test_le_beneficiaire_ne_peut_ni_supprimer_ni_modifier_son_octroi(): void
    {
        $acces = $this->octroyer();

        $this->sousPortailClient(function () use ($acces): void {
            // DELETE/UPDATE d'un octroi ne passent que par la politique d'écriture
            // (propriétaire uniquement) → 0 ligne affectée pour le bénéficiaire.
            $this->assertSame(0, AccesDossier::query()->whereKey($acces->id)->delete());
            $this->assertSame(0, AccesDossier::query()->whereKey($acces->id)->update([
                'statut' => StatutAcces::Revoque->value,
            ]));
        });

        // L'octroi reste actif, vu du propriétaire (révocation douce préservée).
        $this->pourTenant($this->transitaire, fn () => $this->assertSame(
            StatutAcces::Actif,
            AccesDossier::findOrFail($acces->id)->statut,
        ));
    }

    public function test_le_beneficiaire_ne_peut_pas_ecrire_la_projection_partagee(): void
    {
        $this->octroyer();

        $this->sousPortailClient(function (): void {
            // Les politiques de partage sont FOR SELECT : aucune écriture
            // inter-tenant sur la projection (0 ligne affectée).
            $this->assertSame(0, Bl::query()->withoutGlobalScope(TenantScope::class)
                ->update(['navire_nom' => 'PIRATE']));
            $this->assertSame(0, Conteneur::query()->withoutGlobalScope(TenantScope::class)
                ->whereKey($this->conteneurId)->update(['statut' => 'enleve']));
            $this->assertSame(0, SuiviTracking::query()->withoutGlobalScope(TenantScope::class)
                ->update(['emplacement' => 'nulle part']));
        });
    }

    public function test_un_compte_ne_peut_pas_s_auto_octroyer_un_dossier_d_autrui(): void
    {
        // Tentative d'élévation : le client pose son propre tenant comme
        // « propriétaire » d'un dossier qui ne lui appartient pas. La RLS
        // d'écriture (vérification d'appartenance) doit rejeter l'insertion.
        $this->expectException(QueryException::class);

        $this->pourTenant($this->workspaceClient, function (): void {
            AccesDossier::factory()->create([
                'dossier_id' => $this->dossierId,
                'tenant_proprietaire_id' => $this->workspaceClient->id,
                'beneficiaire_user_id' => $this->compteClient->id,
                'beneficiaire_tenant_id' => $this->workspaceClient->id,
                'statut' => StatutAcces::Actif->value,
            ]);
        });
    }

    public function test_un_autre_compte_client_ne_voit_pas_l_octroi_d_autrui(): void
    {
        $this->octroyer();

        // Un second workspace client, non bénéficiaire.
        $autreWorkspace = Tenant::factory()->client()->create();
        $autreCompte = User::factory()->pourTenant($autreWorkspace)->role(RoleUtilisateur::Client)->create();

        $this->pourTenant(
            $autreWorkspace,
            fn () => app(TenantContext::class)->sousPortail($autreCompte->id, function (): void {
                $this->assertSame(0, $this->dossiersVisiblesSansScope());
            }),
        );
    }
}
