<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Data;

use App\Domains\Tracking\Enums\PhaseConteneur;
use Carbon\CarbonImmutable;

/**
 * Résultat neutre d'un suivi de conteneur, isolé du format du fournisseur
 * (principe n°9). `statutBrut` conserve la valeur d'origine (`container_status`)
 * pour journaliser les valeurs non mappées ; `snapshotBrut` alimente la colonne
 * jsonb `suivi_tracking.snapshot` (données métier, jamais de secret).
 */
final class SuiviConteneurData
{
    /**
     * @param  array<string, mixed>  $snapshotBrut
     */
    public function __construct(
        public readonly PhaseConteneur $phase,
        public readonly string $statutBrut,
        public readonly ?string $emplacement,
        public readonly ?CarbonImmutable $etaDestination,
        public readonly ?string $navireNom,
        public readonly ?string $navireImo,
        public readonly array $snapshotBrut,
        public readonly CarbonImmutable $capturedAt,
    ) {}

    /**
     * Empreinte de contenu signifiant (idempotence : un snapshot identique ne
     * doit pas re-déclencher recalcul ni alertes). Exclut `capturedAt` et le
     * payload brut (bruités), garde les faits métier.
     */
    public function empreinte(): string
    {
        return hash('sha256', implode('|', [
            $this->phase->value,
            $this->statutBrut,
            $this->emplacement ?? '',
            $this->etaDestination?->toIso8601String() ?? '',
            $this->navireImo ?? '',
        ]));
    }
}
