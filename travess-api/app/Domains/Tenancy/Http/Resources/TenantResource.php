<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Http\Resources;

use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nom' => $this->nom,
            'plan' => $this->plan->value,
            'statut' => $this->statut->value,
            'quota_ia_mensuel' => $this->quota_ia_mensuel,
            'quota_tracking_mensuel' => $this->quota_tracking_mensuel,
        ];
    }
}
