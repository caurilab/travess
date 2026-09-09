<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Models;

use App\Domains\Conteneurs\Enums\SourceNumeroConteneur;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeConteneur;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ConteneurFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conteneur rattaché à un connaissement.
 *
 * `numero` doit être un numéro ISO 6346 valide (validation via
 * App\Domains\Conteneurs\Support\Iso6346, en amont de la persistance — pas de
 * revalidation en base).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $bl_id
 * @property string $numero
 * @property TypeConteneur $type
 * @property StatutConteneur $statut
 * @property SourceNumeroConteneur $source_numero
 */
final class Conteneur extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'bl_id',
        'numero',
        'type',
        'statut',
        'source_numero',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeConteneur::class,
            'statut' => StatutConteneur::class,
            'source_numero' => SourceNumeroConteneur::class,
        ];
    }

    /**
     * @return BelongsTo<Bl, $this>
     */
    public function bl(): BelongsTo
    {
        return $this->belongsTo(Bl::class);
    }

    /**
     * @return HasMany<Franchise, $this>
     */
    public function franchises(): HasMany
    {
        return $this->hasMany(Franchise::class);
    }

    protected static function newFactory(): Factory
    {
        return ConteneurFactory::new();
    }
}
