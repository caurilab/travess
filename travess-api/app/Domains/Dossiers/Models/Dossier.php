<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Models;

use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Dossiers\Enums\StatutDossier;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\DossierFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Dossier de transit : unité de travail centrale d'un tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 * @property SensDossier $sens
 * @property string $client_id
 * @property StatutDossier $statut
 * @property string|null $motif_blocage
 */
final class Dossier extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'reference',
        'sens',
        'client_id',
        'statut',
        'motif_blocage',
    ];

    protected function casts(): array
    {
        return [
            'sens' => SensDossier::class,
            'statut' => StatutDossier::class,
        ];
    }

    protected static function newFactory(): Factory
    {
        return DossierFactory::new();
    }
}
