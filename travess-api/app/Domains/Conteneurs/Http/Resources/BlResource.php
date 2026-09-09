<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Resources;

use App\Domains\Conteneurs\Models\Bl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Bl
 */
final class BlResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dossier_id' => $this->dossier_id,
            'numero' => $this->numero,
            'armateur_id' => $this->armateur_id,
            'navire_nom' => $this->navire_nom,
            'navire_imo' => $this->navire_imo,
            'conteneurs' => ConteneurResource::collection($this->whenLoaded('conteneurs')),
        ];
    }
}
