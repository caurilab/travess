<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Models;

use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Journal des envois (push / whatsapp / email / desktop) avec statut.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $destinataire_id
 * @property string|null $alerte_id
 * @property CanalNotification $canal
 * @property string $type_evenement
 * @property StatutNotification $statut
 * @property string|null $sujet
 * @property array<string, mixed> $meta
 * @property Carbon|null $envoye_at
 */
final class Notification extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'destinataire_id',
        'alerte_id',
        'canal',
        'type_evenement',
        'statut',
        'sujet',
        'meta',
        'envoye_at',
    ];

    protected function casts(): array
    {
        return [
            'canal' => CanalNotification::class,
            'statut' => StatutNotification::class,
            'meta' => 'array',
            'envoye_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return NotificationFactory::new();
    }
}
