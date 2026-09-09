<?php

declare(strict_types=1);

namespace App\Domains\Ingestion\Http\Resources;

use App\Domains\Documents\Models\ExtractionIa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExtractionIa
 */
final class ExtractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'statut' => $this->statut->value,
            'champs' => $this->champs,
            'corrections' => $this->corrections,
            'valide_par' => $this->valide_par,
            'valide_at' => $this->valide_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
