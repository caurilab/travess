<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Http\Resources;

use App\Domains\Conteneurs\Models\SuiviTracking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dernier suivi d'un conteneur pour la surface agent (vue complète, pas la
 * projection portail). N'expose jamais de secret ; `snapshot` (jsonb) est de la
 * donnée métier normalisée, sans en-tête ni clé fournisseur.
 *
 * @mixin SuiviTracking
 */
final class SuiviTrackingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conteneur_id' => $this->conteneur_id,
            'source' => $this->source->value,
            'statut_conteneur' => $this->statut_conteneur,
            'emplacement' => $this->emplacement,
            'eta_destination' => $this->eta_destination?->toIso8601String(),
            'navire_nom' => $this->navire_nom,
            'navire_imo' => $this->navire_imo,
            'prochain_poll_prevu' => $this->prochain_poll_prevu?->toIso8601String(),
            'captured_at' => $this->captured_at?->toIso8601String(),
        ];
    }
}
