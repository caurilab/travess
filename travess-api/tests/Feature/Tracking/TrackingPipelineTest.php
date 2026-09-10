<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Enums\SourceSuiviTracking;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeConteneur;
use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tracking\Adapters\AdaptateurTrackingFactice;
use App\Domains\Tracking\Data\SuiviConteneurData;
use App\Domains\Tracking\Enums\PhaseConteneur;
use App\Domains\Tracking\Jobs\PollerConteneur;
use App\Domains\Tracking\Services\DecompteConsommationTracking;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Pipeline de tracking de bout en bout avec le fournisseur FACTICE (sans réseau
 * ni clé). Couvre : écriture idempotente du snapshot, mise à jour autoritaire du
 * statut (le tracking est factuel, contrairement à l'IA), recalcul de franchise,
 * décompte de consommation, et bascule (non-trackable / quota atteint).
 */
final class TrackingPipelineTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reflète immédiatement le quota simulé (l'appel /stats est facturé →
        // caché en prod, neutralisé en test).
        config()->set('tracking.cache_stats_ttl', 0);
    }

    private function factice(): AdaptateurTrackingFactice
    {
        return app(AdaptateurTrackingFactice::class);
    }

    /**
     * Crée un conteneur « à traiter » sous un tenant, rattaché à un armateur
     * donné, avec une franchise de détention (active dès l'enlèvement).
     *
     * @return array{Tenant, Conteneur, Franchise}
     */
    private function conteneurAvecFranchise(array $attributsArmateur = []): array
    {
        $tenant = Tenant::factory()->create();

        [$conteneur, $franchise] = $this->pourTenant($tenant, function () use ($attributsArmateur): array {
            $armateur = Armateur::factory()->create($attributsArmateur + [
                'nom' => 'Maersk',
                'nom_api' => 'MAERSK',
                'trackable' => true,
            ]);
            $bl = Bl::factory()->create(['armateur_id' => $armateur->id]);
            $conteneur = Conteneur::factory()->create([
                'bl_id' => $bl->id,
                'type' => TypeConteneur::Vingt,
                'statut' => StatutConteneur::ATraiter,
            ]);
            $franchise = Franchise::factory()->create([
                'conteneur_id' => $conteneur->id,
                'type' => TypeFranchise::Detention,
                'date_debut' => CarbonImmutable::now()->subDays(20),
                'jours_francs' => 5,
                'actif' => false,
            ]);

            return [$conteneur, $franchise];
        });

        return [$tenant, $conteneur, $franchise];
    }

    private function suiviSimule(PhaseConteneur $phase, ?CarbonImmutable $eta = null): SuiviConteneurData
    {
        return new SuiviConteneurData(
            phase: $phase,
            statutBrut: 'FACTICE_'.$phase->value,
            emplacement: 'Terminal — enlevé',
            etaDestination: $eta,
            navireNom: 'EVER GIVEN',
            navireImo: '9811000',
            snapshotBrut: ['source' => 'factice', 'phase' => $phase->value],
            capturedAt: CarbonImmutable::now(),
        );
    }

    private function poller(Tenant $tenant, Conteneur $conteneur, bool $force = true): void
    {
        (new PollerConteneur($tenant->id, $conteneur->id, $force))->handle();
    }

    public function test_un_poll_ecrit_le_suivi_met_a_jour_le_statut_et_la_franchise(): void
    {
        [$tenant, $conteneur, $franchise] = $this->conteneurAvecFranchise();

        // Phase forcée à « enlevé » : le statut doit basculer et la détention s'activer.
        $this->factice()->simuler($this->suiviSimule(PhaseConteneur::Enleve));

        $this->poller($tenant, $conteneur);

        $this->pourTenant($tenant, function () use ($conteneur, $franchise): void {
            $suivi = SuiviTracking::where('conteneur_id', $conteneur->id)->sole();

            $this->assertSame(SourceSuiviTracking::Jsoncargo, $suivi->source);
            $this->assertSame('FACTICE_enleve', $suivi->statut_conteneur);
            $this->assertSame('Terminal — enlevé', $suivi->emplacement);
            $this->assertNotNull($suivi->prochain_poll_prevu);
            $this->assertSame('9811000', $suivi->navire_imo);

            // Le tracking est autoritaire : statut mis à jour selon la phase.
            $this->assertSame(StatutConteneur::Enleve, $conteneur->fresh()->statut);

            // Détention active (enlevé) → franchise recalculée et marquée active.
            $this->assertTrue($franchise->fresh()->actif);
        });

        // Un appel facturé décompté.
        $this->pourTenant($tenant, fn () => $this->assertSame(1, app(DecompteConsommationTracking::class)->quantiteDuMois()));
    }

    public function test_snapshot_identique_est_idempotent(): void
    {
        [$tenant, $conteneur] = $this->conteneurAvecFranchise();

        // Même snapshot simulé pour les deux passages.
        $this->factice()->simuler($this->suiviSimule(PhaseConteneur::Enleve));

        $this->poller($tenant, $conteneur);
        $this->poller($tenant, $conteneur);

        // Pas de 2ᵉ ligne de contenu : le snapshot inchangé ne re-déclenche rien.
        $this->pourTenant($tenant, function () use ($conteneur): void {
            $this->assertSame(1, SuiviTracking::where('conteneur_id', $conteneur->id)->count());
        });

        // Chaque appel fournisseur reste facturé (2 appels), mais le snapshot
        // inchangé n'écrit aucune 2ᵉ ligne de contenu ni ne re-transitionne.
        $this->pourTenant($tenant, fn () => $this->assertSame(2, app(DecompteConsommationTracking::class)->quantiteDuMois()));
    }

    public function test_armateur_non_trackable_bascule_en_manuel_sans_appel(): void
    {
        [$tenant, $conteneur] = $this->conteneurAvecFranchise(['nom' => 'Grimaldi', 'trackable' => false]);

        // Si le factice était appelé, il lèverait : prouve qu'aucun appel n'a lieu.
        $this->factice()->echouera();

        $this->poller($tenant, $conteneur);

        $this->pourTenant($tenant, function () use ($conteneur): void {
            $suivi = SuiviTracking::where('conteneur_id', $conteneur->id)->sole();
            $this->assertSame(SourceSuiviTracking::Manuel, $suivi->source);
            $this->assertNull($suivi->prochain_poll_prevu); // plus de poll automatique
            // Aucun appel facturé.
            $this->assertSame(0, app(DecompteConsommationTracking::class)->quantiteDuMois());
        });
    }

    public function test_quota_atteint_bascule_en_imap_sans_appel(): void
    {
        [$tenant, $conteneur] = $this->conteneurAvecFranchise();

        // Plafond franchi + factice piégé : la garde doit couper avant tout appel.
        $this->factice()->poserQuotaConsomme(0.95)->echouera();

        $this->poller($tenant, $conteneur);

        $this->pourTenant($tenant, function () use ($conteneur): void {
            $suivi = SuiviTracking::where('conteneur_id', $conteneur->id)->sole();
            $this->assertSame(SourceSuiviTracking::Imap, $suivi->source);
            $this->assertNotNull($suivi->prochain_poll_prevu); // reprogrammé (retry ultérieur)
            $this->assertSame(0, app(DecompteConsommationTracking::class)->quantiteDuMois());
        });
    }

    public function test_echec_fournisseur_libere_l_unite_et_n_ecrit_rien(): void
    {
        [$tenant, $conteneur] = $this->conteneurAvecFranchise();

        $this->factice()->echouera();

        try {
            $this->poller($tenant, $conteneur);
            $this->fail('Le job aurait dû propager l\'échec du fournisseur.');
        } catch (\RuntimeException) {
            // attendu
        }

        $this->pourTenant($tenant, function () use ($conteneur): void {
            // Unité comptée puis libérée → retour à 0.
            $this->assertSame(0, app(DecompteConsommationTracking::class)->quantiteDuMois());
            // Aucun snapshot écrit sur un appel non abouti.
            $this->assertSame(0, SuiviTracking::where('conteneur_id', $conteneur->id)->count());
        });
    }

    public function test_conteneur_rendu_n_est_jamais_polle(): void
    {
        [$tenant, $conteneur] = $this->conteneurAvecFranchise();
        $this->pourTenant($tenant, fn () => $conteneur->forceFill(['statut' => StatutConteneur::Rendu->value])->save());

        $this->factice()->echouera(); // prouve l'absence d'appel

        $this->poller($tenant, $conteneur);

        $this->pourTenant($tenant, function () use ($conteneur): void {
            $this->assertSame(0, SuiviTracking::where('conteneur_id', $conteneur->id)->count());
        });
    }
}
