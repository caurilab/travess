<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Support;

use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Tenancy\Models\Tenant;

/**
 * Modèle de workflow par défaut : la liste ordonnée d'étapes appliquée à la
 * création d'un dossier, selon son sens (ADR-006).
 *
 * Source de vérité en code, surchargeable par tenant via
 * `tenant.parametres->workflow->{import|export}` (liste d'objets
 * {libelle, sla_jours}). À la création, les étapes sont COPIÉES (snapshot) dans
 * la table `etapes` : chaque dossier possède ses propres étapes, éditables sans
 * réécrire l'historique. Le paramétrage fin (UI + table dédiée) est un incrément
 * ultérieur qui n'exige aucune migration ici.
 *
 * @phpstan-type LigneEtape array{libelle: string, sla_jours: int}
 */
final class ModeleWorkflow
{
    /**
     * Étapes par défaut d'un import (docs/03 §2.2). SLA indicatifs, éditables.
     *
     * @var list<array{libelle: string, sla_jours: int}>
     */
    private const IMPORT = [
        ['libelle' => 'Annonce / réception documents', 'sla_jours' => 2],
        ['libelle' => 'Manifeste & arrivée navire', 'sla_jours' => 3],
        ['libelle' => 'Déchargement / disponibilité conteneur', 'sla_jours' => 2],
        ['libelle' => 'Dédouanement', 'sla_jours' => 3],
        ['libelle' => 'Enlèvement conteneur', 'sla_jours' => 2],
        ['libelle' => 'Transport / livraison client', 'sla_jours' => 2],
        ['libelle' => 'Restitution conteneur (vide) à l\'armateur', 'sla_jours' => 3],
        ['libelle' => 'Clôture financière', 'sla_jours' => 5],
    ];

    /**
     * Étapes par défaut d'un export (miroir adapté de l'import).
     *
     * @var list<array{libelle: string, sla_jours: int}>
     */
    private const EXPORT = [
        ['libelle' => 'Réception instructions & documents', 'sla_jours' => 2],
        ['libelle' => 'Réservation fret & positionnement conteneur', 'sla_jours' => 3],
        ['libelle' => 'Empotage / chargement', 'sla_jours' => 2],
        ['libelle' => 'Dédouanement export', 'sla_jours' => 3],
        ['libelle' => 'Acheminement au port & mise à quai', 'sla_jours' => 2],
        ['libelle' => 'Embarquement / départ navire', 'sla_jours' => 2],
        ['libelle' => 'Transmission documents (BL, certificats)', 'sla_jours' => 3],
        ['libelle' => 'Clôture financière', 'sla_jours' => 5],
    ];

    /**
     * Résout le modèle d'étapes pour un tenant et un sens : surcharge des
     * paramètres du tenant si présente, sinon défaut en code.
     *
     * @return list<array{libelle: string, sla_jours: int}>
     */
    public static function pour(Tenant $tenant, SensDossier $sens): array
    {
        $surcharge = data_get($tenant->parametres, "workflow.{$sens->value}");

        if (is_array($surcharge) && $surcharge !== []) {
            return self::normaliser($surcharge);
        }

        return $sens === SensDossier::Import ? self::IMPORT : self::EXPORT;
    }

    /**
     * @param  array<int|string, mixed>  $lignes
     * @return list<array{libelle: string, sla_jours: int}>
     */
    private static function normaliser(array $lignes): array
    {
        $resultat = [];

        foreach ($lignes as $ligne) {
            if (! is_array($ligne) || ! isset($ligne['libelle'])) {
                continue;
            }

            $resultat[] = [
                'libelle' => (string) $ligne['libelle'],
                'sla_jours' => (int) ($ligne['sla_jours'] ?? 0),
            ];
        }

        return $resultat;
    }
}
