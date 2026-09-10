<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Resources;

use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Tracking\Support\JalonsParcours;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Conteneur
 */
final class ConteneurResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bl_id' => $this->bl_id,
            'numero' => $this->numero,
            'type' => $this->type->value,
            'statut' => $this->statut->value,
            'source_numero' => $this->source_numero->value,
            // Dernier suivi tracking (si chargé), pour l'affichage « à la MSC ».
            'dernier_suivi' => $this->when(
                $this->relationLoaded('suivis'),
                fn (): ?array => $this->projeterSuivi(),
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function projeterSuivi(): ?array
    {
        $dernier = $this->suivis->sortByDesc('captured_at')->first();
        if (! $dernier instanceof SuiviTracking) {
            return null;
        }

        return [
            'source' => $dernier->source->value,
            'statut_brut' => $dernier->statut_conteneur,
            'emplacement' => $dernier->emplacement,
            'eta_destination' => $dernier->eta_destination?->toIso8601String(),
            'navire_nom' => $dernier->navire_nom,
            'navire_imo' => $dernier->navire_imo,
            'prochain_poll_prevu' => $dernier->prochain_poll_prevu?->toIso8601String(),
            'capture_le' => $dernier->captured_at?->toIso8601String(),
            // Frise datée du parcours (origine → position → destination).
            'jalons' => JalonsParcours::depuis($dernier->snapshot ?? []),
        ];
    }
}
