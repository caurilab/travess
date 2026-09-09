<?php

declare(strict_types=1);

namespace App\Domains\Finances\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ChargeFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Charge (argent sorti) rattachée à un dossier.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string $libelle
 * @property string $montant
 * @property bool $avancee_pour_client
 */
final class Charge extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'libelle',
        'montant',
        'avancee_pour_client',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'avancee_pour_client' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ChargeFactory::new();
    }
}
