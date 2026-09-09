<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Models;

use App\Domains\Conteneurs\Enums\SourceSuiviTracking;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\SuiviTrackingFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Snapshot de suivi d'un conteneur (JSONCargo / IMAP / manuel).
 *
 * prochain_poll_prevu est calculé par le scheduler intelligent (shared-core).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $conteneur_id
 * @property SourceSuiviTracking $source
 * @property array<string, mixed> $snapshot
 * @property string|null $statut_conteneur
 * @property string|null $emplacement
 * @property Carbon|null $eta_destination
 * @property string|null $navire_nom
 * @property string|null $navire_imo
 * @property Carbon|null $prochain_poll_prevu
 * @property Carbon|null $captured_at
 */
final class SuiviTracking extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'suivi_tracking';

    protected $fillable = [
        'conteneur_id',
        'source',
        'snapshot',
        'statut_conteneur',
        'emplacement',
        'eta_destination',
        'navire_nom',
        'navire_imo',
        'prochain_poll_prevu',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => SourceSuiviTracking::class,
            'snapshot' => 'array',
            'eta_destination' => 'datetime',
            'prochain_poll_prevu' => 'datetime',
            'captured_at' => 'datetime',
        ];
    }

    protected static function newFactory(): Factory
    {
        return SuiviTrackingFactory::new();
    }
}
