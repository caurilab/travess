<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Services;

use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Surestaries\Support\CalculFranchise;
use Illuminate\Support\Carbon;

/**
 * Recalcule les champs dérivés d'une franchise (date_fin_franchise,
 * montant_en_cours, montant_menacant, actif) via le port unique CalculFranchise.
 *
 * Ne persiste pas : mute le modèle en mémoire, l'appelant décide d'enregistrer.
 * Utilisé par l'écriture (Actions), la lecture fraîche (contrôleur) et le job
 * de recalcul planifié — une seule source de vérité.
 */
final class RecalculFranchise
{
    /** Horizon du « montant menaçant » (aligné sur l'alerte J-3). */
    public const HORIZON_MENACANT_JOURS = 3;

    public function recalculer(Franchise $franchise, ?Carbon $dateEvaluation = null): Franchise
    {
        $franchise->loadMissing('conteneur.bl.armateur');

        $conteneur = $franchise->conteneur;
        $armateur = $conteneur->bl->armateur;

        $bareme = $franchise->type === TypeFranchise::Surestaries
            ? $armateur->bareme_surestaries
            : $armateur->bareme_detention;

        $resultat = CalculFranchise::calculer([
            'type' => $franchise->type->value,
            'date_debut' => $franchise->date_debut->toDateString(),
            'jours_francs' => $franchise->jours_francs,
            'bareme' => $bareme, // colonne jsonb NOT NULL default '{}' : toujours un tableau
            'type_conteneur' => $conteneur->type->value,
            'statut_conteneur' => $conteneur->statut->value,
            'date_evaluation' => ($dateEvaluation ?? Carbon::now())->toDateString(),
            'horizon_menacant_jours' => self::HORIZON_MENACANT_JOURS,
        ]);

        $franchise->forceFill([
            'date_fin_franchise' => $resultat['date_fin_franchise'],
            'montant_en_cours' => $resultat['montant_en_cours'],
            'montant_menacant' => $resultat['montant_menacant'],
            'actif' => $resultat['actif'],
        ]);

        return $franchise;
    }
}
