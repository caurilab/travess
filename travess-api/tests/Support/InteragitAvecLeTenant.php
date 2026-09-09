<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;

/**
 * Aide de test : exécuter du code dans le contexte d'un tenant donné.
 */
trait InteragitAvecLeTenant
{
    protected function contexteTenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    /**
     * Exécute le callback avec le tenant courant positionné, puis le réinitialise.
     *
     * @template T
     *
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function pourTenant(Tenant|string $tenant, \Closure $callback): mixed
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $this->contexteTenant()->set($id);

        try {
            return $callback();
        } finally {
            $this->contexteTenant()->forget();
        }
    }
}
