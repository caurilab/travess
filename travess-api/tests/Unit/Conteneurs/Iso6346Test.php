<?php

declare(strict_types=1);

namespace Tests\Unit\Conteneurs;

use App\Domains\Conteneurs\Support\Iso6346;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validation ISO 6346 (port PHP) éprouvée contre les vecteurs de test PARTAGÉS
 * (packages/test-vectors/iso6346.json). Les mêmes vecteurs alimentent la
 * batterie TypeScript (packages/shared-core) : toute divergence entre les deux
 * ports casse la CI. C'est la garantie du « écrit une fois » (spécification +
 * vecteurs), le principe non négociable n°1 appliqué entre deux runtimes.
 */
final class Iso6346Test extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function vecteurs(): array
    {
        $chemin = dirname(__DIR__, 4).'/packages/test-vectors/iso6346.json';
        $contenu = file_get_contents($chemin);

        if ($contenu === false) {
            throw new \RuntimeException("Vecteurs ISO 6346 introuvables : {$chemin}");
        }

        return json_decode($contenu, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{string}>
     */
    public static function numerosValides(): array
    {
        return array_map(static fn (string $n): array => [$n], self::vecteurs()['valides']);
    }

    /**
     * @return list<array{string, string}>
     */
    public static function numerosInvalides(): array
    {
        return array_map(
            static fn (array $e): array => [$e['numero'], $e['raison']],
            self::vecteurs()['invalides'],
        );
    }

    /**
     * @return list<array{string, int}>
     */
    public static function chiffresDeControle(): array
    {
        return array_map(
            static fn (array $c): array => [$c['prefixe'], $c['attendu']],
            self::vecteurs()['chiffres_de_controle'],
        );
    }

    #[DataProvider('numerosValides')]
    public function test_accepte_les_numeros_valides(string $numero): void
    {
        $this->assertTrue(Iso6346::estValide($numero), "devrait accepter {$numero}");
    }

    #[DataProvider('numerosInvalides')]
    public function test_rejette_les_numeros_invalides(string $numero, string $raison): void
    {
        $this->assertFalse(Iso6346::estValide($numero), "devrait rejeter « {$numero} » ({$raison})");
    }

    #[DataProvider('chiffresDeControle')]
    public function test_calcule_le_chiffre_de_controle(string $prefixe, int $attendu): void
    {
        $this->assertSame($attendu, Iso6346::chiffreDeControle($prefixe));
    }

    public function test_normalise_vers_la_forme_canonique(): void
    {
        $this->assertSame('CSQU3054383', Iso6346::normaliser('csqu 3054383'));
        $this->assertTrue(Iso6346::estValide(Iso6346::normaliser('csqu 3054383')));
    }
}
