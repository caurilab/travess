<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Models;

use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\FranchiseFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Franchise (surestaries / détention) d'un conteneur.
 *
 * Les champs date_fin_franchise, montant_en_cours, montant_menacant et actif
 * sont CALCULÉS par shared-core (jamais saisis à la main, docs/07 §12).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $conteneur_id
 * @property TypeFranchise $type
 * @property Carbon $date_debut
 * @property int $jours_francs
 * @property Carbon|null $date_fin_franchise
 * @property string $montant_en_cours
 * @property string $montant_menacant
 * @property bool $actif
 */
final class Franchise extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'conteneur_id',
        'type',
        'date_debut',
        'jours_francs',
        'date_fin_franchise',
        'montant_en_cours',
        'montant_menacant',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeFranchise::class,
            'date_debut' => 'date',
            'jours_francs' => 'integer',
            'date_fin_franchise' => 'date',
            'montant_en_cours' => 'decimal:2',
            'montant_menacant' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    protected static function newFactory(): Factory
    {
        return FranchiseFactory::new();
    }
}
