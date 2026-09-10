<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Http\Resources;

use App\Domains\Correspondance\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'dossier_id' => $this->dossier_id,
            'armateur_id' => $this->armateur_id,
            'auteur_id' => $this->auteur_id,
            'direction' => $this->direction->value,
            'type_demande' => $this->type_demande?->value,
            'canal' => $this->canal->value,
            'destinataire_adresse' => $this->destinataire_adresse,
            'objet' => $this->objet,
            'corps' => $this->corps,
            'statut' => $this->statut->value,
            'reference_externe' => $this->reference_externe,
            'erreur' => $this->erreur,
            'envoye_at' => $this->envoye_at?->toIso8601String(),
            'recu_at' => $this->recu_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
