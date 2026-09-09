<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Models;

use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Enums\PlanTenant;
use App\Domains\Tenancy\Enums\StatutTenant;
use App\Domains\Tenancy\Enums\TypeTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Le tenant : une société de transit. Racine de l'isolation multi-tenant.
 *
 * N'utilise PAS BelongsToTenant : il n'appartient à aucun tenant, il en est un.
 *
 * @property string $id
 * @property string $nom
 * @property TypeTenant $type
 * @property bool $annuaire_public
 * @property PlanTenant $plan
 * @property StatutTenant $statut
 * @property int $quota_ia_mensuel
 * @property int $quota_tracking_mensuel
 * @property array<string, mixed> $parametres
 */
final class Tenant extends BaseModel
{
    protected $fillable = [
        'nom',
        'type',
        'annuaire_public',
        'plan',
        'statut',
        'quota_ia_mensuel',
        'quota_tracking_mensuel',
        'parametres',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeTenant::class,
            'annuaire_public' => 'boolean',
            'plan' => PlanTenant::class,
            'statut' => StatutTenant::class,
            'quota_ia_mensuel' => 'integer',
            'quota_tracking_mensuel' => 'integer',
            'parametres' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return TenantFactory::new();
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
