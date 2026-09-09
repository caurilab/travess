<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Immutabilité append-only de audit_log : les mises à jour et suppressions sont
 * rejetées au niveau base (trigger), quel que soit le chemin.
 */
final class AuditLogAppendOnlyTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private function unAudit(Tenant $tenant): AuditLog
    {
        return $this->pourTenant($tenant, fn (): AuditLog => AuditLog::create([
            'entite' => 'dossier',
            'entite_id' => (string) Str::uuid7(),
            'action' => 'test.creation',
            'at' => now(),
        ]));
    }

    public function test_un_update_sur_audit_log_est_rejete(): void
    {
        $tenant = Tenant::factory()->create();
        $audit = $this->unAudit($tenant);

        $this->expectException(QueryException::class);

        $this->pourTenant($tenant, fn () => DB::table('audit_log')->where('id', $audit->id)->update(['action' => 'falsifie']));
    }

    public function test_un_delete_sur_audit_log_est_rejete(): void
    {
        $tenant = Tenant::factory()->create();
        $audit = $this->unAudit($tenant);

        $this->expectException(QueryException::class);

        $this->pourTenant($tenant, fn () => DB::table('audit_log')->where('id', $audit->id)->delete());
    }
}
