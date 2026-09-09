<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Garde-fou d'architecture (ADR-008) : les contrôleurs ne mutent pas l'état
 * directement — toute mutation passe par une Action (transactionnelle + auditée).
 * Un contrôleur qui ferait un save/update/delete/create ou du DB:: brut serait
 * un chemin de mutation non audité : ce test le refuse.
 */
final class MutationsPassentParActionsTest extends TestCase
{
    private const MOTIFS_INTERDITS = [
        '->save(',
        '->update(',
        '->delete(',
        '->forceDelete(',
        '->forceFill(',
        '->increment(',
        '->decrement(',
        '->sync(',
        '->attach(',
        '->detach(',
        '::create(',
        '::insert(',
        '::updateOrCreate(',
        '::firstOrCreate(',
        'DB::table(',
        'DB::insert(',
        'DB::update(',
        'DB::delete(',
        'DB::statement(',
    ];

    /**
     * Domaines exemptés : l'auth (Identity) mute via Fortify/Sanctum (révocation
     * de jeton, activation 2FA) — opérations d'infrastructure d'authentification,
     * hors de la convention Actions métier.
     */
    private const DOMAINES_EXEMPTES = ['Identity'];

    public function test_aucun_controleur_ne_mute_l_etat_directement(): void
    {
        $fichiers = glob(dirname(__DIR__, 2).'/../app/Domains/*/Http/Controllers/*.php') ?: [];
        $this->assertNotEmpty($fichiers, 'Aucun contrôleur trouvé : le garde-fou ne garantirait rien.');

        foreach ($fichiers as $fichier) {
            foreach (self::DOMAINES_EXEMPTES as $domaine) {
                if (str_contains($fichier, "/Domains/{$domaine}/")) {
                    continue 2;
                }
            }

            $contenu = (string) file_get_contents($fichier);

            foreach (self::MOTIFS_INTERDITS as $motif) {
                $this->assertStringNotContainsString(
                    $motif,
                    $contenu,
                    basename($fichier)." contient « {$motif} » : une mutation doit passer par une Action, pas par le contrôleur.",
                );
            }
        }
    }
}
