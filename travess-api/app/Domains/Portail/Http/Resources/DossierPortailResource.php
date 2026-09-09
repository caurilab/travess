<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Dossier projeté pour la vue LIMITÉE du client (ADR-013) : identité du dossier
 * + BL + parcours, et RIEN d'autre. On n'expose jamais motif_blocage (info
 * commerciale) ni les finances/documents/étapes (hors projection).
 *
 * @mixin Dossier
 */
final class DossierPortailResource extends JsonResource
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
            'bls' => BlPortailResource::collection($this->whenLoaded('bls')),
        ];
    }
}
