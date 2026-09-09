<?php

declare(strict_types=1);

namespace App\Domains\Finances\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\EncaissementFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Encaissement (argent entré) rattaché à un dossier, avec rapprochement
 * optionnel vers une charge.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string $montant
 * @property Carbon $date
 * @property string|null $rapproche_charge_id
 */
final class Encaissement extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'montant',
        'date',
        'rapproche_charge_id',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'date' => 'date',
        ];
    }

    protected static function newFactory(): Factory
    {
        return EncaissementFactory::new();
    }
}
