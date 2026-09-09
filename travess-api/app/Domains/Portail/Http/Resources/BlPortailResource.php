<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Conteneurs\Models\Bl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BL projeté pour le portail : contenu du connaissement + ses conteneurs. Le
 * navire est identifié par IMO (clé fiable) ; navire_nom reste indicatif.
 *
 * @mixin Bl
 */
final class BlPortailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'navire_nom' => $this->navire_nom,
            'navire_imo' => $this->navire_imo,
            'conteneurs' => ConteneurPortailResource::collection($this->conteneurs),
        ];
    }
}
