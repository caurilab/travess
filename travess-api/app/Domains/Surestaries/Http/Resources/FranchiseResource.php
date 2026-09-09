<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Http\Resources;

use App\Domains\Conteneurs\Models\Franchise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Franchise
 */
final class FranchiseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conteneur_id' => $this->conteneur_id,
            'type' => $this->type->value,
            'date_debut' => $this->date_debut->toDateString(),
            'jours_francs' => $this->jours_francs,
            'date_fin_franchise' => $this->date_fin_franchise?->toDateString(),
            'montant_en_cours' => $this->montant_en_cours,
            'montant_menacant' => $this->montant_menacant,
            'actif' => $this->actif,
        ];
    }
}
