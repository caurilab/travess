<?php

declare(strict_types=1);

namespace App\Domains\Audit\Http\Resources;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
final class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'entite' => $this->entite,
            'entite_id' => $this->entite_id,
            'action' => $this->action,
            'avant' => $this->avant,
            'apres' => $this->apres,
            'at' => $this->at->toIso8601String(),
        ];
    }
}
