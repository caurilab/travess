<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Domains\Tracking\Enums\PhaseConteneur;
use App\Domains\Tracking\Support\CalculProchainPoll;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Cœur de l'économie d'appels (docs/08 §2.2) : la fréquence de poll dépend de la
 * phase, de la fenêtre de franchise et de la proximité de l'ETA. Fonction pure,
 * mais lit la table de fréquences via config('tracking.*') — on fixe la config
 * explicitement pour rendre le test indépendant de l'environnement.
 */
final class CalculProchainPollTest extends TestCase
{
    private CarbonImmutable $maintenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Table de fréquences canonique (docs/08 §2.2), posée explicitement.
        config()->set('tracking.seuil_approche_jours', 3);
        config()->set('tracking.frequences', [
            'en_mer' => 7,
            'approche' => 1,
            'franchise' => 1,
            'enleve' => 30,
        ]);

        $this->maintenant = CarbonImmutable::create(2026, 6, 1, 12, 0, 0);
    }

    private function calculer(PhaseConteneur $phase, bool $franchiseActive, ?CarbonImmutable $eta): ?CarbonImmutable
    {
        return CalculProchainPoll::calculer($phase, $franchiseActive, $eta, $this->maintenant);
    }

    public function test_rendu_ne_declenche_plus_aucun_poll(): void
    {
        $this->assertNull($this->calculer(PhaseConteneur::Rendu, false, null));
    }

    public function test_rendu_prime_meme_avec_franchise_active(): void
    {
        // Le filtre « rendu » est prioritaire : plus jamais interrogé.
        $this->assertNull($this->calculer(PhaseConteneur::Rendu, true, null));
    }

    public function test_franchise_active_force_le_quotidien(): void
    {
        $attendu = $this->maintenant->addDays(1);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::EnMer, true, null));
    }

    public function test_franchise_active_prime_sur_la_phase(): void
    {
        // Même une phase « enlevé » (rare, +30) passe au quotidien sous franchise.
        $attendu = $this->maintenant->addDays(1);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::Enleve, true, null));
    }

    public function test_en_mer_sans_eta_est_hebdomadaire(): void
    {
        $attendu = $this->maintenant->addDays(7);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::EnMer, false, null));
    }

    public function test_en_mer_avec_eta_lointaine_reste_hebdomadaire(): void
    {
        $eta = $this->maintenant->addDays(5); // au-delà du seuil (3)
        $attendu = $this->maintenant->addDays(7);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::EnMer, false, $eta));
    }

    public function test_en_mer_avec_eta_dans_le_seuil_bascule_quotidien(): void
    {
        $eta = $this->maintenant->addDays(2); // dans le seuil (3)
        $attendu = $this->maintenant->addDays(1);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::EnMer, false, $eta));
    }

    public function test_en_mer_avec_eta_pile_au_seuil_bascule_quotidien(): void
    {
        $eta = $this->maintenant->addDays(3); // bornes incluses
        $attendu = $this->maintenant->addDays(1);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::EnMer, false, $eta));
    }

    public function test_en_mer_avec_eta_passee_reste_hebdomadaire(): void
    {
        $eta = $this->maintenant->subDays(1); // ETA dépassée → non « proche »
        $attendu = $this->maintenant->addDays(7);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::EnMer, false, $eta));
    }

    public function test_approche_est_quotidien(): void
    {
        $attendu = $this->maintenant->addDays(1);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::Approche, false, null));
    }

    public function test_decharge_est_quotidien(): void
    {
        $attendu = $this->maintenant->addDays(1);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::Decharge, false, null));
    }

    public function test_enleve_hors_franchise_est_rare(): void
    {
        $attendu = $this->maintenant->addDays(30);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::Enleve, false, null));
    }

    public function test_livre_hors_franchise_est_rare(): void
    {
        $attendu = $this->maintenant->addDays(30);

        $this->assertEquals($attendu, $this->calculer(PhaseConteneur::Livre, false, null));
    }
}
