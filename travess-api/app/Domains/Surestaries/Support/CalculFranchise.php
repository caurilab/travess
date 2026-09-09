<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Support;

/**
 * Calcul des surestaries / détention (port PHP — source de vérité serveur).
 *
 * Barème PROGRESSIF : chaque jour facturé est compté au tarif du palier où il
 * tombe. Jours CALENDAIRES. date_debut = jour 1 de franchise ; date_fin_franchise
 * = dernier jour gratuit ; jour entamé = jour dû. Montants ENTIERS (XOF).
 *
 * ⚠️ Cœur métier dupliqué par nécessité entre runtimes (PHP API + TS clients).
 * Le port TypeScript (packages/shared-core/src/surestaries) doit rester
 * équivalent ; parité garantie par packages/test-vectors/surestaries.json.
 */
final class CalculFranchise
{
    private const SECONDES_PAR_JOUR = 86400;

    /**
     * @param  array{
     *     type: string,
     *     date_debut: string,
     *     jours_francs: int,
     *     bareme: array<string, mixed>,
     *     type_conteneur: string,
     *     statut_conteneur: string,
     *     date_evaluation: string,
     *     horizon_menacant_jours: int
     * }  $entree
     * @return array{
     *     date_fin_franchise: string,
     *     premier_jour_facture: string,
     *     montant_en_cours: int,
     *     montant_menacant: int,
     *     actif: bool,
     *     jours_factures: int
     * }
     */
    public static function calculer(array $entree): array
    {
        $paliers = self::resoudrePaliers($entree['bareme'], $entree['type_conteneur']);

        $debut = self::indiceJour($entree['date_debut']);
        $finFranchise = $debut + $entree['jours_francs'] - 1;
        $premierFacture = $finFranchise + 1;
        $evaluation = self::indiceJour($entree['date_evaluation']);

        $montantEnCours = 0;
        $joursFactures = 0;
        for ($d = $premierFacture; $d <= $evaluation; $d++) {
            $montantEnCours += self::tarifJour($paliers, $d - $premierFacture + 1);
            $joursFactures++;
        }

        $montantMenacant = 0;
        $debutHorizon = max($evaluation + 1, $premierFacture);
        for ($d = $debutHorizon; $d <= $evaluation + $entree['horizon_menacant_jours']; $d++) {
            $montantMenacant += self::tarifJour($paliers, $d - $premierFacture + 1);
        }

        return [
            'date_fin_franchise' => self::versDate($finFranchise),
            'premier_jour_facture' => self::versDate($premierFacture),
            'montant_en_cours' => $montantEnCours,
            'montant_menacant' => $montantMenacant,
            'actif' => self::estActif($entree['type'], $entree['statut_conteneur']),
            'jours_factures' => $joursFactures,
        ];
    }

    /**
     * @param  array<string, mixed>  $bareme
     * @return list<array{de_jour: int, a_jour: int|null, tarif_jour: int}>
     */
    public static function resoudrePaliers(array $bareme, string $typeConteneur): array
    {
        $parType = $bareme['paliers_par_type'] ?? [];

        return $parType[$typeConteneur] ?? $parType['defaut'] ?? [];
    }

    public static function estActif(string $type, string $statutConteneur): bool
    {
        if ($statutConteneur === 'rendu') {
            return false;
        }

        if ($type === 'surestaries') {
            return $statutConteneur === 'a_traiter';
        }

        return $statutConteneur === 'enleve' || $statutConteneur === 'livre';
    }

    /**
     * @param  list<array{de_jour: int, a_jour: int|null, tarif_jour: int}>  $paliers
     */
    private static function tarifJour(array $paliers, int $k): int
    {
        foreach ($paliers as $palier) {
            if ($k >= $palier['de_jour'] && ($palier['a_jour'] === null || $k <= $palier['a_jour'])) {
                return (int) $palier['tarif_jour'];
            }
        }

        return 0;
    }

    /** Indice de jour (jours depuis l'époque, en UTC — insensible au fuseau). */
    private static function indiceJour(string $date): int
    {
        return intdiv((int) strtotime($date.' 00:00:00 UTC'), self::SECONDES_PAR_JOUR);
    }

    private static function versDate(int $indice): string
    {
        return gmdate('Y-m-d', $indice * self::SECONDES_PAR_JOUR);
    }
}
