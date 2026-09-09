<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreerClientJobDeTest;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Un job tenant-scopé écrit bien dans le tenant ciblé, et son écriture n'est pas
 * visible depuis un autre tenant (étanchéité en file, dette F-1 résorbée).
 */
final class JobTenantScopedTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    public function test_un_job_ecrit_dans_le_tenant_cible_sans_fuite(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        // Exécution synchrone (QUEUE_CONNECTION=sync en test).
        CreerClientJobDeTest::dispatch($a->id, 'Client via job A');

        $this->pourTenant($a, function (): void {
            $this->assertSame(1, Client::count());
            $this->assertSame('Client via job A', Client::first()?->nom);
        });

        // Rien n'a fuité vers B.
        $this->pourTenant($b, fn () => $this->assertSame(0, Client::count()));
    }

    public function test_le_contexte_est_libere_apres_le_job(): void
    {
        $a = Tenant::factory()->create();

        CreerClientJobDeTest::dispatch($a->id, 'X');

        // Après le job, plus de contexte : le fail-closed reprend.
        $this->assertFalse($this->contexteTenant()->hasTenant());
    }
}
