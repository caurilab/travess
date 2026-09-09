<?php

declare(strict_types=1);

namespace App\Domains\Finances\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\HonoraireFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Honoraire (facturation du transitaire) avec numérotation légale continue.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string $montant
 * @property string $numero_facture
 * @property string $pdf_chemin
 */
final class Honoraire extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'montant',
        'numero_facture',
        'pdf_chemin',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
        ];
    }

    protected static function newFactory(): Factory
    {
        return HonoraireFactory::new();
    }
}
