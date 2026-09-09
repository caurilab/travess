<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Models;

use App\Domains\Alertes\Enums\StatutAlerte;
use App\Domains\Alertes\Enums\TypeAlerte;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\AlerteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alerte métier (paliers surestaries/détention, SLA dépassé, blocage).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string|null $conteneur_id
 * @property TypeAlerte $type
 * @property string|null $montant_menacant
 * @property StatutAlerte $statut
 * @property array<string, mixed> $canaux_envoyes
 */
final class Alerte extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'conteneur_id',
        'type',
        'montant_menacant',
        'statut',
        'canaux_envoyes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeAlerte::class,
            'montant_menacant' => 'decimal:2',
            'statut' => StatutAlerte::class,
            'canaux_envoyes' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return AlerteFactory::new();
    }
}
