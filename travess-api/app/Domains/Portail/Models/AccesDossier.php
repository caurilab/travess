<?php

declare(strict_types=1);

namespace App\Domains\Portail\Models;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Shared\Models\BaseModel;
use Database\Factories\AccesDossierFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Octroi d'accès partagé à un dossier (ADR-013). Table inter-tenant PAR NATURE :
 * elle relie le tenant propriétaire d'un dossier au tenant bénéficiaire d'un
 * accès. Elle N'UTILISE PAS BelongsToTenant (pas de scoping par un unique
 * tenant) et n'a pas de FK composite (id, tenant_id) — exception documentée à
 * ADR-004. Sa protection est une politique RLS dédiée (bénéficiaire OU tenant
 * propriétaire OU tenant bénéficiaire OU bypass), pas le TenantScope.
 *
 * L'accès est nominatif (beneficiaire_user_id), en LECTURE seule, et seul le
 * statut « actif » ouvre la lecture (fail-closed).
 *
 * @property string $id
 * @property string $dossier_id
 * @property string $tenant_proprietaire_id
 * @property string $beneficiaire_user_id
 * @property string $beneficiaire_tenant_id
 * @property NiveauAcces $niveau
 * @property StatutAcces $statut
 * @property OrigineAcces $origine
 * @property string|null $created_by
 * @property Carbon|null $revoked_at
 */
final class AccesDossier extends BaseModel
{
    protected $table = 'acces_dossier';

    protected $fillable = [
        'dossier_id',
        'tenant_proprietaire_id',
        'beneficiaire_user_id',
        'beneficiaire_tenant_id',
        'niveau',
        'statut',
        'origine',
        'created_by',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'niveau' => NiveauAcces::class,
            'statut' => StatutAcces::class,
            'origine' => OrigineAcces::class,
            'revoked_at' => 'datetime',
        ];
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
        return AccesDossierFactory::new();
    }
}
