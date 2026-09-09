<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue ÉTENDUE (restreinte) du dossier autonome (ADR-013, 7.3a) : le client
 * détient le dossier dans son workspace. Projection en liste blanche — dossier
 * + étapes (parcours) + BL + conteneurs. Pas de champs internes transitaire.
 *
 * @mixin Dossier
 */
final class DossierAutonomeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sens' => $this->sens->value,
            'statut' => $this->statut->value,
            'posture' => $this->posture->value,
            'etapes' => EtapePortailResource::collection($this->whenLoaded('etapes')),
            'bls' => BlPortailResource::collection($this->whenLoaded('bls')),
        ];
    }
}
