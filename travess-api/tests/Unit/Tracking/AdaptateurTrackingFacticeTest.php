<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Domains\Conteneurs\Support\Iso6346;
use App\Domains\Tracking\Adapters\AdaptateurTrackingFactice;
use App\Domains\Tracking\Data\SuiviConteneurData;
use App\Domains\Tracking\Enums\PhaseConteneur;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Le fournisseur factice est le socle de toute la suite tracking : il doit être
 * déterministe, sans réseau, et produire des numéros ISO 6346 réellement
 * valides. On y vérifie aussi les leviers de test (simuler / echouera /
 * poserQuotaConsomme).
 */
final class AdaptateurTrackingFacticeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ancre temporelle figée : le cycle de phase par défaut dépend du temps.
        CarbonImmutable::setTestNow(CarbonImmutable::create(2026, 6, 1, 8, 0, 0));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_conteneurs_depuis_bl_sont_iso_6346_valides(): void
    {
        $numeros = (new AdaptateurTrackingFactice)->conteneursDepuisBl('BL-2026-001', 'MAERSK');

        $this->assertNotEmpty($numeros);
        $this->assertLessThanOrEqual(3, count($numeros));
        foreach ($numeros as $numero) {
            $this->assertTrue(Iso6346::estValide($numero), "numéro factice invalide : {$numero}");
        }
    }

    public function test_conteneurs_depuis_bl_est_deterministe(): void
    {
        $a = (new AdaptateurTrackingFactice)->conteneursDepuisBl('BL-2026-001', 'MAERSK');
        $b = (new AdaptateurTrackingFactice)->conteneursDepuisBl('BL-2026-001', 'MAERSK');

        $this->assertSame($a, $b);
    }

    public function test_simuler_force_le_resultat_exact(): void
    {
        $attendu = new SuiviConteneurData(
            phase: PhaseConteneur::Enleve,
            statutBrut: 'FORCE',
            emplacement: 'Terminal',
            etaDestination: null,
            navireNom: 'X',
            navireImo: '9999999',
            snapshotBrut: ['k' => 'v'],
            capturedAt: CarbonImmutable::now(),
        );

        $adaptateur = (new AdaptateurTrackingFactice)->simuler($attendu);

        $this->assertSame($attendu, $adaptateur->suivreConteneur('MSCU7390252', 'MSC'));
    }

    public function test_echouera_leve_une_exception(): void
    {
        $this->expectException(RuntimeException::class);

        (new AdaptateurTrackingFactice)->echouera()->suivreConteneur('MSCU7390252', 'MSC');
    }

    public function test_suivi_par_defaut_est_deterministe_a_temps_fige(): void
    {
        $a = (new AdaptateurTrackingFactice)->suivreConteneur('MSCU7390252', 'MSC');
        $b = (new AdaptateurTrackingFactice)->suivreConteneur('MSCU7390252', 'MSC');

        // Même empreinte de contenu signifiant (base de l'idempotence).
        $this->assertSame($a->empreinte(), $b->empreinte());
    }

    public function test_poser_quota_consomme_pilote_les_stats(): void
    {
        $stats = (new AdaptateurTrackingFactice)->poserQuotaConsomme(0.60)->statsQuota();

        $this->assertEqualsWithDelta(0.60, $stats->pourcentageConsomme(), 0.01);
    }

    public function test_quota_est_borne_entre_0_et_1(): void
    {
        $factice = new AdaptateurTrackingFactice;

        $this->assertSame(1.0, $factice->poserQuotaConsomme(5.0)->statsQuota()->pourcentageConsomme());
        $this->assertSame(0.0, $factice->poserQuotaConsomme(-1.0)->statsQuota()->pourcentageConsomme());
    }

    public function test_resout_le_navire_par_imo_a_sept_chiffres(): void
    {
        $navire = (new AdaptateurTrackingFactice)->resoudreNavireImo('EVER GIVEN');

        $this->assertMatchesRegularExpression('/^\d{7}$/', $navire->imo);
        $this->assertSame('EVER GIVEN', $navire->nom);
        $this->assertNotNull($navire->mmsi);
    }
}
