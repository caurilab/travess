<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Portail\Models\DemandeAssignation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DemandeAssignation
 */
final class DemandeAssignationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dossier_id' => $this->dossier_id,
            'statut' => $this->statut->value,
            'transitaire_cible_id' => $this->transitaire_cible_id,
            'tenant_demandeur_id' => $this->tenant_demandeur_id,
            'motif_refus' => $this->motif_refus,
            'expire_at' => $this->expire_at->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
