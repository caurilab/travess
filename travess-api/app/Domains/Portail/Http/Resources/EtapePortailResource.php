<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Dossiers\Models\Etape;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Étape du parcours projetée pour le portail (liste blanche).
 *
 * @mixin Etape
 */
final class EtapePortailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ordre' => $this->ordre,
            'libelle' => $this->libelle,
            'statut' => $this->statut->value,
            'date_prevue' => $this->date_prevue?->toDateString(),
            'date_reelle' => $this->date_reelle?->toDateString(),
        ];
    }
}
