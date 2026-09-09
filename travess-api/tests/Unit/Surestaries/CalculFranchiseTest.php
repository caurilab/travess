<?php

declare(strict_types=1);

namespace Tests\Unit\Surestaries;

use App\Domains\Surestaries\Support\CalculFranchise;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Calcul surestaries/détention (port PHP) éprouvé contre les vecteurs PARTAGÉS
 * (packages/test-vectors/surestaries.json), les mêmes que la batterie TypeScript
 * (packages/shared-core). Toute divergence entre les deux ports casse la CI —
 * garantie du « écrit une fois » (spécification + vecteurs), principe n°1.
 */
final class CalculFranchiseTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function vecteurs(): array
    {
        $chemin = dirname(__DIR__, 4).'/packages/test-vectors/surestaries.json';
        $contenu = file_get_contents($chemin);

        if ($contenu === false) {
            throw new \RuntimeException("Vecteurs surestaries introuvables : {$chemin}");
        }

        return json_decode($contenu, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{string, array<string, mixed>, array<string, mixed>, array<string, mixed>}>
     */
    public static function cas(): array
    {
        $vecteurs = self::vecteurs();

        return array_map(
            static fn (array $c): array => [
                $c['nom'],
                $c['entree'],
                $c['attendu'],
                $vecteurs['baremes'][$c['entree']['bareme_ref']],
            ],
            $vecteurs['cas'],
        );
    }

    /**
     * @param  array<string, mixed>  $entree
     * @param  array<string, mixed>  $attendu
     * @param  array<string, mixed>  $bareme
     */
    #[DataProvider('cas')]
    public function test_calcul_conforme_aux_vecteurs(string $nom, array $entree, array $attendu, array $bareme): void
    {
        $resultat = CalculFranchise::calculer([
            'type' => $entree['type'],
            'date_debut' => $entree['date_debut'],
            'jours_francs' => $entree['jours_francs'],
            'bareme' => $bareme,
            'type_conteneur' => $entree['type_conteneur'],
            'statut_conteneur' => $entree['statut_conteneur'],
            'date_evaluation' => $entree['date_evaluation'],
            'horizon_menacant_jours' => $entree['horizon_menacant_jours'],
        ]);

        $this->assertSame($attendu, $resultat, "Cas : {$nom}");
    }
}
