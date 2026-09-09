<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Http\Resources;

use App\Domains\Conteneurs\Models\Conteneur;
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
        ];
    }
}
