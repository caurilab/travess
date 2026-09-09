<?php

declare(strict_types=1);

namespace App\Domains\Alertes\Models;

use App\Domains\Alertes\Enums\StatutAlerte;
use App\Domains\Alertes\Enums\TypeAlerte;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Dossiers\Models\Dossier;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\AlerteFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alerte métier (paliers surestaries/détention, SLA dépassé, blocage).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string|null $conteneur_id
 * @property TypeAlerte $type
 * @property int|null $montant_menacant
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
            'montant_menacant' => 'integer', // XOF sans sous-unité
            'statut' => StatutAlerte::class,
            'canaux_envoyes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<Conteneur, $this>
     */
    public function conteneur(): BelongsTo
    {
        return $this->belongsTo(Conteneur::class);
    }

    protected static function newFactory(): Factory
    {
        return AlerteFactory::new();
    }
}
