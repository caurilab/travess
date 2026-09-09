<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Models;

use App\Domains\Dossiers\Enums\StatutEtape;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\EtapeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Étape de workflow d'un dossier (avec SLA éditable).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property int $ordre
 * @property string $libelle
 * @property int $sla_jours
 * @property Carbon|null $date_prevue
 * @property Carbon|null $date_reelle
 * @property StatutEtape $statut
 * @property string|null $responsable_id
 */
final class Etape extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'ordre',
        'libelle',
        'sla_jours',
        'date_prevue',
        'date_reelle',
        'statut',
        'responsable_id',
    ];

    protected function casts(): array
    {
        return [
            'ordre' => 'integer',
            'sla_jours' => 'integer',
            'date_prevue' => 'date',
            'date_reelle' => 'date',
            'statut' => StatutEtape::class,
        ];
    }

    protected static function newFactory(): Factory
    {
        return EtapeFactory::new();
    }
}
