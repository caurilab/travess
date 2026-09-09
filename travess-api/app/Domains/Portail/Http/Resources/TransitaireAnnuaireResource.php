<?php

declare(strict_types=1);

namespace App\Domains\Portail\Http\Resources;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche publique d'un transitaire dans l'annuaire (ADR-013, 7.4). Liste blanche :
 * on n'expose que l'identité minimale nécessaire au choix, jamais de données
 * internes du tenant.
 *
 * @mixin Tenant
 */
final class TransitaireAnnuaireResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
        ];
    }
}
