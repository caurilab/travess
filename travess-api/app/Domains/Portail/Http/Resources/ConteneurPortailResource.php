<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Conteneurs\Models\Conteneur;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Conteneur projeté pour le portail : identité + statut + dernier parcours.
 *
 * @mixin Conteneur
 */
final class ConteneurPortailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dernier = $this->suivis->sortByDesc('captured_at')->first();

        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'type' => $this->type->value,
            'statut' => $this->statut->value,
            'parcours' => $dernier !== null ? ParcoursPortailResource::make($dernier) : null,
        ];
    }
}
