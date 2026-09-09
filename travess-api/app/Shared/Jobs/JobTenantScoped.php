<?php

declare(strict_types=1);

namespace App\Shared\Jobs;

use App\Shared\Context\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base des jobs travaillant sur les données d'UN tenant.
 *
 * Le tenant est sérialisé à la mise en file et le contexte (mémoire + GUC
 * PostgreSQL pour la RLS) est rétabli à l'exécution via TenantContext::pour().
 * Ainsi RLS, TenantScope, le forçage de tenant_id à la création et l'audit se
 * comportent exactement comme en HTTP. Un job métier ne doit JAMAIS toucher un
 * modèle scopé hors de ce cadre (fail-closed sinon).
 *
 * L'orchestration (énumérer les tenants, dispatcher un job par tenant) se fait
 * en amont, jamais par une boucle inter-tenant unique dans un même job.
 */
abstract class JobTenantScoped implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
    ) {}

    final public function handle(): void
    {
        app(TenantContext::class)->pour($this->tenantId, fn () => $this->traiter());
    }

    /**
     * Le travail réel, exécuté avec le contexte tenant établi.
     */
    abstract protected function traiter(): void;
}
