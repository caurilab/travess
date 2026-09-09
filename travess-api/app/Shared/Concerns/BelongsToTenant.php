<?php

declare(strict_types=1);

namespace App\Shared\Concerns;

use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use App\Shared\Exceptions\TenantContextMissingException;
use App\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rend un modèle strictement scopé à son tenant.
 *
 * Trois garanties :
 *  1. Toute lecture est filtrée par le tenant courant (TenantScope).
 *  2. À la création, le tenant_id est forcé depuis le contexte — un tenant_id
 *     fourni par le client est ignoré et écrasé (principe non négociable n°3).
 *  3. Sans contexte tenant, création et lecture échouent (fail-closed).
 *
 * À utiliser sur TOUS les modèles métier. Seuls les modèles d'amorçage
 * (Tenant, User) et l'infrastructure Laravel/Sanctum en sont exemptés — voir
 * la liste blanche testée dans tests/Feature/Tenancy.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($context->isBypassed()) {
                return;
            }

            if (! $context->hasTenant()) {
                throw TenantContextMissingException::forCreation($model::class);
            }

            // Le tenant vient toujours du contexte, jamais de l'entrée client.
            $model->setAttribute('tenant_id', $context->id());
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, $this->getTenantColumn());
    }

    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    public function getQualifiedTenantColumn(): string
    {
        return $this->getTable().'.'.$this->getTenantColumn();
    }

    /**
     * Requête explicitement non filtrée par tenant (usage système/audit).
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Route-model binding tenant-sûr : la résolution du binding pouvant
     * précéder le middleware tenant, on désactive le global scope (sinon il
     * lèverait faute de contexte) et on filtre explicitement par le tenant
     * courant, à défaut celui de l'utilisateur authentifié. Une cible d'un
     * autre tenant est introuvable (404), jamais résolue globalement.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $contexte = app(TenantContext::class);
        $tenantId = $contexte->id() ?? auth()->user()?->tenant_id;

        if ($tenantId === null) {
            return null;
        }

        // La résolution du binding précède le middleware tenant : le GUC RLS
        // n'est pas encore posé. On lève la RLS le temps de cette lecture, mais
        // le filtre explicite par tenant_id garantit qu'aucune ligne d'un autre
        // tenant ne peut être résolue (404 pour l'inter-tenant).
        return $contexte->runBypassed(fn (): ?Model => static::withoutGlobalScope(TenantScope::class)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('tenant_id', $tenantId)
            ->first());
    }
}
