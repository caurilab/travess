<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Support;

use App\Domains\Tracking\Enums\PhaseConteneur;
use Carbon\CarbonImmutable;

/**
 * Calcule la date du prochain poll d'un conteneur (docs/08 §2.2, §2.3) — le
 * cœur de l'économie d'appels : on ne réveille un conteneur que lorsque quelque
 * chose est susceptible d'avoir changé.
 *
 * Fonction PURE (entrées primitives, aucune dépendance Eloquent) : promouvable
 * vers packages/shared-core/src/prochain-poll/ le jour où une surface client
 * voudra afficher « prochaine mise à jour prévue ». Les fréquences sont
 * paramétrables via config('tracking.*') sans redéploiement de logique.
 */
final class CalculProchainPoll
{
    public static function calculer(
        PhaseConteneur $phase,
        bool $franchiseActive,
        ?CarbonImmutable $eta,
        CarbonImmutable $maintenant,
    ): ?CarbonImmutable {
        // Conteneur rendu : plus jamais interrogé.
        if ($phase === PhaseConteneur::Rendu) {
            return null;
        }

        /** @var array<string, int|null> $freq */
        $freq = config('tracking.frequences', []);
        $seuilApproche = (int) config('tracking.seuil_approche_jours', 3);

        // Fenêtre de franchise (surestaries/détention) : quotidien, prioritaire.
        if ($franchiseActive) {
            return $maintenant->addDays((int) ($freq['franchise'] ?? 1));
        }

        $jours = match ($phase) {
            // En mer : hebdomadaire, sauf ETA proche → bascule quotidien.
            PhaseConteneur::EnMer => self::etaProche($eta, $maintenant, $seuilApproche)
                ? (int) ($freq['approche'] ?? 1)
                : (int) ($freq['en_mer'] ?? 7),
            // À l'approche / fraîchement déchargé : surveillance rapprochée.
            PhaseConteneur::Approche, PhaseConteneur::Decharge => (int) ($freq['approche'] ?? 1),
            // Enlevé / livré hors franchise : rare, à la demande.
            // (Rendu est déjà filtré par le retour anticipé plus haut.)
            PhaseConteneur::Enleve, PhaseConteneur::Livre => (int) ($freq['enleve'] ?? 30),
        };

        return $maintenant->addDays($jours);
    }

    private static function etaProche(?CarbonImmutable $eta, CarbonImmutable $maintenant, int $seuilJours): bool
    {
        if ($eta === null) {
            return false;
        }

        $jours = $maintenant->diffInDays($eta, false);

        return $jours >= 0 && $jours <= $seuilJours;
    }
}
