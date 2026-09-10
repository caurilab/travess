<?php

declare(strict_types=1);

namespace Tests\Unit\Tracking;

use App\Domains\Tracking\Support\JalonsParcours;
use Tests\TestCase;

/**
 * JalonsParcours dérive une frise datée (origine → position → destination) du
 * snapshot fournisseur. Fonction pure : on vérifie l'ordre, le marquage des
 * états, la déduplication des lieux et la robustesse aux champs manquants.
 */
final class JalonsParcoursTest extends TestCase
{
    /** Snapshot type JSONCargo « en mer » : quatre jalons attendus, position au départ. */
    public function test_frise_en_mer_ordonne_et_marque_les_etats(): void
    {
        $snapshot = [
            'container_status' => 'Export Loaded on Vessel',
            'shipped_from' => 'Nansha, CN',
            'shipped_from_terminal' => 'Nansha ICT',
            'atd_origin' => '2026-08-04 00:00',
            'last_location' => 'Nansha, CN',
            'last_movement_timestamp' => '2026-08-04 00:00',
            'next_location' => 'Abidjan, CI',
            'eta_next_destination' => '2026-09-21 00:00',
            'shipped_to' => 'Abidjan, CI',
            'shipped_to_terminal' => 'CIT',
            'eta_final_destination' => '2026-09-21 00:00',
            'current_vessel_name' => 'MSC ISTANBUL',
        ];

        $jalons = JalonsParcours::depuis($snapshot);

        // Nansha (départ == position, dédupliqué) puis Abidjan (escale == destination, dédupliqué).
        self::assertCount(2, $jalons);
        self::assertSame('position', $jalons[0]['code']);
        self::assertSame('actuel', $jalons[0]['etat']);
        self::assertSame('Nansha, CN', $jalons[0]['lieu']);
        self::assertSame('MSC ISTANBUL', $jalons[0]['navire']);

        self::assertSame('Abidjan, CI', $jalons[1]['lieu']);
        self::assertSame('prevu', $jalons[1]['etat']);
        self::assertTrue($jalons[1]['date_estimee']);
    }

    /** Conteneur arrivé : la destination devient la position ; pas d'escale future. */
    public function test_frise_arrive_a_destination(): void
    {
        $snapshot = [
            'container_status' => 'Discharged',
            'shipped_from' => 'Le Havre, FR',
            'atd_origin' => '2026-07-01 00:00',
            'last_location' => 'Abidjan, CI',
            'last_movement_timestamp' => '2026-08-20 00:00',
            'next_location' => null,
            'shipped_to' => 'Abidjan, CI',
            'eta_final_destination' => '2026-08-20 00:00',
        ];

        $jalons = JalonsParcours::depuis($snapshot);

        self::assertCount(2, $jalons);
        self::assertSame('Le Havre, FR', $jalons[0]['lieu']);
        self::assertSame('fait', $jalons[0]['etat']);
        self::assertSame('position', $jalons[1]['code']);
        self::assertSame('actuel', $jalons[1]['etat']);
        self::assertSame('Abidjan, CI', $jalons[1]['lieu']);
    }

    /** Snapshot factice minimal (sans clés de route) : aucune frise, pas d'erreur. */
    public function test_snapshot_sans_lieux_donne_frise_vide(): void
    {
        self::assertSame([], JalonsParcours::depuis(['source' => 'factice', 'phase' => 'en_mer']));
        self::assertSame([], JalonsParcours::depuis([]));
    }

    /** Une date illisible est neutralisée (null) sans faire échouer la dérivation. */
    public function test_date_invalide_devient_null(): void
    {
        $jalons = JalonsParcours::depuis([
            'shipped_from' => 'Tanger Med, MA',
            'atd_origin' => 'pas-une-date',
            'shipped_to' => 'Abidjan, CI',
        ]);

        self::assertNotSame([], $jalons);
        self::assertNull($jalons[0]['date']);
    }
}
