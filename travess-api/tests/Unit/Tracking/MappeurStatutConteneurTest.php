<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Tracking\Enums\PhaseConteneur;
use App\Domains\Tracking\Support\MappeurStatutConteneur;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Traduction phase fine (tracking) → statut métier canonique. Plusieurs phases
 * amont (en mer / approche / déchargé) retombent sur « à traiter » : le
 * conteneur n'est pas encore pris en charge côté transitaire. Fonction pure.
 */
final class MappeurStatutConteneurTest extends TestCase
{
    /**
     * @return list<array{PhaseConteneur, StatutConteneur}>
     */
    public static function correspondances(): array
    {
        return [
            'en mer → à traiter' => [PhaseConteneur::EnMer, StatutConteneur::ATraiter],
            'approche → à traiter' => [PhaseConteneur::Approche, StatutConteneur::ATraiter],
            'déchargé → à traiter' => [PhaseConteneur::Decharge, StatutConteneur::ATraiter],
            'enlevé → enlevé' => [PhaseConteneur::Enleve, StatutConteneur::Enleve],
            'livré → livré' => [PhaseConteneur::Livre, StatutConteneur::Livre],
            'rendu → rendu' => [PhaseConteneur::Rendu, StatutConteneur::Rendu],
        ];
    }

    #[DataProvider('correspondances')]
    public function test_chaque_phase_mappe_le_bon_statut(PhaseConteneur $phase, StatutConteneur $attendu): void
    {
        $this->assertSame($attendu, MappeurStatutConteneur::versStatut($phase));
    }

    public function test_toutes_les_phases_sont_couvertes(): void
    {
        // Garde-fou : si une phase est ajoutée sans mapping, le match() lèvera.
        foreach (PhaseConteneur::cases() as $phase) {
            $this->assertInstanceOf(StatutConteneur::class, MappeurStatutConteneur::versStatut($phase));
        }
    }
}
