<?php

declare(strict_types=1);

namespace App\Domains\Audit\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit inaltérable (append-only) : trace des actions par tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $user_id
 * @property string $entite
 * @property string $entite_id
 * @property string $action
 * @property array<string, mixed>|null $avant
 * @property array<string, mixed>|null $apres
 * @property Carbon $at
 */
final class AuditLog extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'audit_log';

    // Journal append-only : l'horodatage porté par « at », pas de created_at/updated_at.
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'entite',
        'entite_id',
        'action',
        'avant',
        'apres',
        'at',
    ];

    protected function casts(): array
    {
        return [
            'avant' => 'array',
            'apres' => 'array',
            'at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AuditLogFactory::new();
    }
}
