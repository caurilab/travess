<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Http\Resources;

use App\Domains\Alertes\Models\Alerte;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Alerte
 */
final class AlerteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dossier_id' => $this->dossier_id,
            'conteneur_id' => $this->conteneur_id,
            'type' => $this->type->value,
            'montant_menacant' => $this->montant_menacant,
            'statut' => $this->statut->value,
            'canaux_envoyes' => $this->canaux_envoyes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
