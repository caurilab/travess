<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Domains\Tracking\Adapters\AdaptateurTrackingFactice;
use App\Domains\Tracking\Contracts\FournisseurTracking;
use App\Domains\Tracking\Services\GardeQuotaTracking;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Plafond de sécurité du quota MUTUALISÉ (docs/08 §3) : coupure du polling auto
 * dès ~90 %, alerte interne dès 75 %. On pilote le quota via l'adaptateur
 * factice singleton, et on désactive le cache des stats (TTL 0) pour refléter
 * immédiatement le quota simulé.
 */
final class GardeQuotaTrackingTest extends TestCase
{
    private AdaptateurTrackingFactice $factice;

    protected function setUp(): void
    {
        parent::setUp();

        // Seuils canoniques + cache neutralisé (sinon le premier pourcentage lu
        // resterait figé pour le run).
        config()->set('tracking.plafond_coupure', 0.90);
        config()->set('tracking.plafond_alerte', 0.75);
        config()->set('tracking.cache_stats_ttl', 0);

        // Singleton partagé avec GardeQuotaTracking (injecté via FournisseurTracking).
        $this->factice = app(AdaptateurTrackingFactice::class);
        $this->app->instance(FournisseurTracking::class, $this->factice);
    }

    private function garde(): GardeQuotaTracking
    {
        return app(GardeQuotaTracking::class);
    }

    public function test_autorise_l_appel_sous_le_plafond(): void
    {
        $this->factice->poserQuotaConsomme(0.5);

        $this->assertTrue($this->garde()->autoriseAppel());
        $this->assertSame(0.5, $this->garde()->pourcentageConsomme());
    }

    public function test_coupe_l_appel_au_dela_du_plafond(): void
    {
        $this->factice->poserQuotaConsomme(0.95);

        $this->assertFalse($this->garde()->autoriseAppel());
    }

    public function test_coupe_pile_au_plafond_de_coupure(): void
    {
        // 0.90 exactement : le seuil est fermé (>=), donc on coupe.
        $this->factice->poserQuotaConsomme(0.90);

        $this->assertFalse($this->garde()->autoriseAppel());
    }

    public function test_alerte_interne_emise_une_seule_fois_des_75_pourcent(): void
    {
        Log::spy();
        $this->factice->poserQuotaConsomme(0.80); // ≥ 75 % (alerte) mais < 90 % (pas de coupure)

        $this->assertTrue($this->garde()->autoriseAppel());
        $this->garde()->autoriseAppel(); // second passage le même mois

        // Alerte ops idempotente (Cache::add une fois par mois).
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_pas_d_alerte_sous_75_pourcent(): void
    {
        Log::spy();
        $this->factice->poserQuotaConsomme(0.5);

        $this->garde()->autoriseAppel();

        Log::shouldNotHaveReceived('warning');
    }

    public function test_le_cache_des_stats_fige_le_pourcentage_quand_ttl_positif(): void
    {
        // TTL positif : le premier pourcentage lu est mémorisé et ne bouge plus
        // du run (l'appel /stats est lui-même facturé).
        Cache::flush();
        config()->set('tracking.cache_stats_ttl', 300);

        $this->factice->poserQuotaConsomme(0.10);
        $this->assertSame(0.10, $this->garde()->pourcentageConsomme());

        $this->factice->poserQuotaConsomme(0.95); // changement ignoré tant que le cache tient
        $this->assertSame(0.10, $this->garde()->pourcentageConsomme());
    }
}
