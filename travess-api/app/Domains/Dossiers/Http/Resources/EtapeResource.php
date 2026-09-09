<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Resources;

use App\Domains\Dossiers\Models\Etape;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Etape
 */
final class EtapeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ordre' => $this->ordre,
            'libelle' => $this->libelle,
            'sla_jours' => $this->sla_jours,
            'date_prevue' => $this->date_prevue?->toDateString(),
            'date_reelle' => $this->date_reelle?->toDateString(),
            'statut' => $this->statut->value,
            'responsable_id' => $this->responsable_id,
        ];
    }
}
