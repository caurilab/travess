<?php

declare(strict_types=1);

namespace App\Domains\Identity\Models;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Utilisateur d'un tenant.
 *
 * NON auto-scopé par TenantScope : il participe à l'amorçage de
 * l'authentification (résolution par email avant établissement du contexte).
 * Le confinement par tenant des opérations de gestion des utilisateurs se fait
 * explicitement via le scope forTenant() + une policy (jamais User::all() nu
 * dans un contexte tenant). Ce choix est audité (voir tests d'étanchéité).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $nom
 * @property string $email
 * @property RoleUtilisateur $role
 * @property array<string, mixed> $preferences_notif
 */
final class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasUuids;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'nom',
        'email',
        'password',
        'role',
        'preferences_notif',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'preferences_notif' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Restreint explicitement au tenant fourni (le scoping des utilisateurs
     * n'étant pas automatique). À employer systématiquement pour toute liste
     * ou recherche d'utilisateurs dans un contexte tenant.
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Route-model binding scopé : en présence d'un contexte tenant, un {user}
     * d'un autre tenant est introuvable (404), ce qui neutralise l'IDOR
     * inter-tenant malgré l'absence d'auto-scope sur User.
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        $query = self::query()->where($field ?? $this->getRouteKeyName(), $value);

        // La résolution du binding peut précéder le middleware tenant : on se
        // rabat sur le tenant de l'utilisateur authentifié (déjà résolu à ce stade).
        $tenantId = app(TenantContext::class)->id() ?? auth()->user()?->tenant_id;

        // Fail-closed : sans tenant résoluble, aucune cible (jamais de résolution
        // globale par id, qui traverserait les tenants).
        if ($tenantId === null) {
            return null;
        }

        return $query->where('tenant_id', $tenantId)->first();
    }

    protected static function newFactory(): Factory
    {
        return UserFactory::new();
    }
}
