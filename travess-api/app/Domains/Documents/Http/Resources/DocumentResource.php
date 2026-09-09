<?php

declare(strict_types=1);

namespace App\Domains\Documents\Http\Resources;

use App\Domains\Documents\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
final class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dossier_id' => $this->dossier_id,
            'type' => $this->type->value,
            'origine' => $this->origine->value,
            'statut_ingestion' => $this->statut_ingestion->value,
            'nom_original' => $this->nom_original,
            'mime' => $this->mime,
            'taille' => $this->taille,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
