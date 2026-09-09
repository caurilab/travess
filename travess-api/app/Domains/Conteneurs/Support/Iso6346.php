<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Support;

use InvalidArgumentException;

/**
 * Validation ISO 6346 du numéro de conteneur (port PHP — source de vérité serveur).
 *
 * Forme canonique : 3 lettres (code propriétaire) + 1 lettre de catégorie
 * (U, J ou Z) + 6 chiffres (série) + 1 chiffre de contrôle. 11 caractères,
 * majuscules, sans espace.
 *
 * ⚠️ Cœur métier dupliqué par nécessité entre runtimes (PHP API + TS clients).
 * Le port TypeScript (packages/shared-core) doit rester équivalent. La parité
 * est garantie par les vecteurs partagés packages/test-vectors/iso6346.json,
 * consommés par les deux batteries de tests. Toute divergence casse la CI.
 */
final class Iso6346
{
    private const FORME_CANONIQUE = '/^[A-Z]{3}[UJZ][0-9]{6}[0-9]$/';

    /**
     * Nettoie une saisie vers la forme canonique (majuscules, sans espace).
     */
    public static function normaliser(string $entree): string
    {
        return strtoupper(preg_replace('/\s+/', '', $entree) ?? '');
    }

    /**
     * Vrai si le numéro (forme canonique attendue) est un numéro ISO 6346 valide.
     */
    public static function estValide(string $numero): bool
    {
        if (preg_match(self::FORME_CANONIQUE, $numero) !== 1) {
            return false;
        }

        $attendu = self::chiffreDeControle(substr($numero, 0, 10));
        $fourni = (int) $numero[10];

        return $attendu === $fourni;
    }

    /**
     * Chiffre de contrôle des 10 premiers caractères (4 lettres + 6 chiffres).
     * Un reste de 10 est ramené à 0 (convention ISO 6346).
     */
    public static function chiffreDeControle(string $prefixe): int
    {
        if (preg_match('/^[A-Z]{4}[0-9]{6}$/', $prefixe) !== 1) {
            throw new InvalidArgumentException("Préfixe ISO 6346 invalide : {$prefixe}");
        }

        $somme = 0;
        for ($i = 0; $i < 10; $i++) {
            $somme += self::valeurCaractere($prefixe[$i]) * (2 ** $i);
        }

        $reste = $somme % 11;

        return $reste === 10 ? 0 : $reste;
    }

    private static function valeurCaractere(string $caractere): int
    {
        if ($caractere >= '0' && $caractere <= '9') {
            return (int) $caractere;
        }

        return self::valeurLettre($caractere);
    }

    /**
     * Valeur ISO 6346 d'une lettre : A = 10, en sautant les multiples de 11.
     */
    private static function valeurLettre(string $lettre): int
    {
        $code = ord($lettre);
        $n = 10;

        for ($c = 65 /* A */; $c <= 90 /* Z */; $c++) {
            if ($n % 11 === 0) {
                $n++;
            }
            if ($c === $code) {
                return $n;
            }
            $n++;
        }

        throw new InvalidArgumentException("Lettre hors A-Z : {$lettre}");
    }
}
