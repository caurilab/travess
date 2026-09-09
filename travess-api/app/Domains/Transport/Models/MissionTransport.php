<?php

declare(strict_types=1);

namespace App\Domains\Transport\Models;

use App\Domains\Transport\Enums\StatutMission;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\MissionTransportFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Mission de transport terrestre (livraison), avec trace géolocalisée.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string|null $chauffeur_id
 * @property string|null $camion
 * @property StatutMission $statut
 * @property array<int, mixed> $positions
 * @property string|null $lien_suivi_public
 * @property string|null $bon_livraison_doc_id
 */
final class MissionTransport extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'missions_transport';

    protected $fillable = [
        'dossier_id',
        'chauffeur_id',
        'camion',
        'statut',
        'positions',
        'lien_suivi_public',
        'bon_livraison_doc_id',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutMission::class,
            'positions' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return MissionTransportFactory::new();
    }
}
