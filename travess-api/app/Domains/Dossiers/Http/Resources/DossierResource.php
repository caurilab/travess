<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Http\Resources;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Http\Resources\UserResource;
use App\Domains\Tenancy\Http\Resources\ClientResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Dossier
 *
 * Ressource unique dossier : les champs de base en liste, enrichie via
 * whenLoaded pour le détail (étapes, client, agents, BL, documents). Les
 * résumés finances/transport sont posés comme placeholders pour figer la forme
 * du détail — ils seront alimentés aux lots 3 (finances) et 4+ (transport).
 */
final class DossierResource extends JsonResource
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
            'motif_blocage' => $this->motif_blocage,
            'client_id' => $this->client_id,
            'created_at' => $this->created_at?->toIso8601String(),

            'client' => new ClientResource($this->whenLoaded('client')),
            'etapes' => EtapeResource::collection($this->whenLoaded('etapes')),
            'agents' => UserResource::collection($this->whenLoaded('agents')),

            // Résumés à forme figée (remplis aux lots ultérieurs), présents dès
            // que le détail est chargé (étapes chargées = vue détail).
            'finances' => $this->whenLoaded('etapes', fn (): array => [
                'charges' => 0,
                'encaissements' => 0,
                'honoraires' => 0,
                'solde' => 0,
            ]),
            'transport' => $this->whenLoaded('etapes', fn () => null),
        ];
    }
}
