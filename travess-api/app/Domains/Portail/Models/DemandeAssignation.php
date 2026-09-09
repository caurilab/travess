<?php

declare(strict_types=1);

namespace App\Domains\Portail\Models;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Enums\StatutDemande;
use App\Shared\Models\BaseModel;
use Database\Factories\DemandeAssignationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande d'assignation d'un transitaire (ADR-013, 7.3b). Table inter-tenant :
 * PAS de BelongsToTenant (protégée par RLS dédiée lecture/insertion/décision).
 *
 * @property string $id
 * @property string $dossier_id
 * @property string $tenant_demandeur_id
 * @property string $transitaire_cible_id
 * @property StatutDemande $statut
 * @property string|null $message
 * @property string|null $motif_refus
 * @property string|null $created_by
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property Carbon $expire_at
 */
final class DemandeAssignation extends BaseModel
{
    protected $table = 'demande_assignation';

    protected $fillable = [
        'dossier_id',
        'tenant_demandeur_id',
        'transitaire_cible_id',
        'statut',
        'message',
        'motif_refus',
        'created_by',
        'decided_by',
        'decided_at',
        'expire_at',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutDemande::class,
            'message' => 'encrypted',
            'decided_at' => 'datetime',
            'expire_at' => 'datetime',
        ];
    }

    public function estDecidable(): bool
    {
        return $this->statut === StatutDemande::EnAttente && $this->expire_at->isFuture();
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    protected static function newFactory(): Factory
    {
        return DemandeAssignationFactory::new();
    }
}
