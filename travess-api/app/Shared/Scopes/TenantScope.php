<?php

declare(strict_types=1);

namespace App\Shared\Scopes;

use App\Shared\Context\TenantContext;
use App\Shared\Exceptions\TenantContextMissingException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope appliqué à tout modèle utilisant le trait BelongsToTenant.
 *
 * Injecte systématiquement « where tenant_id = <tenant courant> » sur chaque
 * requête. En l'absence de contexte tenant, il lève une exception (fail-closed)
 * plutôt que de renvoyer les lignes de tous les tenants.
 *
 * Échappatoire : les requêtes système explicitement non scopées passent par
 * TenantContext::runBypassed() ou Model::withoutGlobalScope(TenantScope::class).
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        if (! $context->hasTenant()) {
            throw TenantContextMissingException::forModel($model::class);
        }

        /** @var \App\Shared\Concerns\BelongsToTenant $model */
        $builder->where($model->getQualifiedTenantColumn(), $context->id());
    }
}
