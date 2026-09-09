<?php

declare(strict_types=1);

namespace App\Domains\Consommation\Models;

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ConsommationServiceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Compteur de consommation par service et par période (paliers, dépassement).
 *
 * @property string $id
 * @property string $tenant_id
 * @property ServiceConsomme $service
 * @property string $periode
 * @property int $quantite
 */
final class ConsommationService extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'consommation_service';

    protected $fillable = [
        'service',
        'periode',
        'quantite',
    ];

    protected function casts(): array
    {
        return [
            'service' => ServiceConsomme::class,
            'quantite' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ConsommationServiceFactory::new();
    }
}
