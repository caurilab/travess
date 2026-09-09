<?php

declare(strict_types=1);

namespace App\Domains\Conteneurs\Models;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Dossiers\Models\Dossier;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\BlFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Connaissement (Bill of Lading) rattaché à un dossier.
 *
 * Le navire est identifié par son IMO (clé fiable) ; navire_nom est indicatif
 * (docs/07 §12).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string $numero
 * @property string $armateur_id
 * @property string|null $navire_nom
 * @property string|null $navire_imo
 */
final class Bl extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'bls';

    protected $fillable = [
        'dossier_id',
        'numero',
        'armateur_id',
        'navire_nom',
        'navire_imo',
    ];

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<Armateur, $this>
     */
    public function armateur(): BelongsTo
    {
        return $this->belongsTo(Armateur::class);
    }

    /**
     * @return HasMany<Conteneur, $this>
     */
    public function conteneurs(): HasMany
    {
        return $this->hasMany(Conteneur::class);
    }

    protected static function newFactory(): Factory
    {
        return BlFactory::new();
    }
}
