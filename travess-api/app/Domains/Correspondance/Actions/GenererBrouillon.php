<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Actions;

use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Dossiers\Models\Dossier;

/**
 * Compose un brouillon (objet + corps) pré-rempli pour une demande armateur.
 *
 * Non persisté : l'agent édite le texte puis l'envoie (principe n°5, l'humain
 * garde la main). Le corps est du texte simple (retours à la ligne). Les
 * montants menaçants proviennent du snapshot de franchise (rafraîchi par le job
 * planifié) — suffisant pour un brouillon relu par un humain.
 */
final class GenererBrouillon
{
    /**
     * @return array{objet: string, corps: string, type_demande: string}
     */
    public function executer(Dossier $dossier, TypeDemande $type): array
    {
        $dossier->loadMissing('bls.armateur', 'bls.conteneurs.franchises');

        $bl = $dossier->bls->first();
        $numeroBl = $bl?->numero ?? $dossier->reference;
        $armateur = $bl?->armateur?->nom ?? "l'armateur";

        [$objet, $corps] = match ($type) {
            TypeDemande::RelanceSurestaries => $this->relanceSurestaries($dossier, $numeroBl, $armateur),
            TypeDemande::Reclamation => [
                "Réclamation — dossier {$numeroBl}",
                "Bonjour,\n\nNous revenons vers vous concernant le connaissement {$numeroBl} "
                    ."et souhaitons signaler le point suivant :\n\n[Précisez votre réclamation]\n\n"
                    .$this->signature(),
            ],
            TypeDemande::DemandeBl => [
                "Demande de connaissement — {$numeroBl}",
                "Bonjour,\n\nNous vous prions de bien vouloir nous transmettre le connaissement "
                    ."original {$numeroBl} afin de poursuivre les opérations de dédouanement.\n\n"
                    .$this->signature(),
            ],
            TypeDemande::DemandeDo => [
                "Demande de bon à délivrer (DO) — {$numeroBl}",
                "Bonjour,\n\nNous sollicitons l'émission du bon à délivrer (Delivery Order) pour le "
                    ."connaissement {$numeroBl}, les formalités et paiements requis étant en cours de "
                    ."finalisation.\n\n".$this->signature(),
            ],
            TypeDemande::Autre => [
                "Dossier {$numeroBl}",
                "Bonjour,\n\n[Votre message]\n\n".$this->signature(),
            ],
        };

        return ['objet' => $objet, 'corps' => $corps, 'type_demande' => $type->value];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function relanceSurestaries(Dossier $dossier, string $numeroBl, string $armateur): array
    {
        $lignes = [];
        $total = 0;
        foreach ($dossier->bls as $bl) {
            foreach ($bl->conteneurs as $conteneur) {
                foreach ($conteneur->franchises as $franchise) {
                    if (! $franchise->actif) {
                        continue;
                    }
                    $fin = $franchise->date_fin_franchise?->toDateString() ?? 'n/c';
                    $lignes[] = "- Conteneur {$conteneur->numero} : fin de franchise le {$fin}";
                    $total += (int) $franchise->montant_menacant;
                }
            }
        }

        $liste = $lignes === [] ? "- (conteneurs du dossier)" : implode("\n", $lignes);
        $menace = $total > 0
            ? "\n\nLe risque de surestaries est estimé à ".number_format($total, 0, ',', ' ')." FCFA.\n"
            : "\n";

        $corps = "Bonjour,\n\nAfin d'éviter des frais de surestaries sur le connaissement {$numeroBl}, "
            ."nous vous prions d'accélérer le traitement des conteneurs suivants :\n\n{$liste}{$menace}\n"
            ."Merci de nous confirmer la disponibilité dans les meilleurs délais.\n\n".$this->signature();

        return ["Relance surestaries — dossier {$numeroBl}", $corps];
    }

    private function signature(): string
    {
        return "Cordialement,\nVotre transitaire";
    }
}
