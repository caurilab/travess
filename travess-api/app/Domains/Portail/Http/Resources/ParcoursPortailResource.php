<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Conteneurs\Models\SuiviTracking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Parcours (tracking) projeté en LISTE BLANCHE pour le portail client (ADR-013,
 * m1 de l'audit 7.0) : on n'expose JAMAIS le snapshot brut du fournisseur, ni la
 * source, ni les champs d'ordonnancement interne (prochain_poll_prevu).
 *
 * @mixin SuiviTracking
 */
final class ParcoursPortailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'statut_conteneur' => $this->statut_conteneur,
            'emplacement' => $this->emplacement,
            'eta_destination' => $this->eta_destination?->toIso8601String(),
            'navire_nom' => $this->navire_nom,
            'capture_le' => $this->captured_at?->toIso8601String(),
        ];
    }
}
