<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Défense en profondeur base : la Row-Level Security PostgreSQL confine les
 * accès qui échappent au scoping Eloquent (requête brute DB::table, insert de
 * masse). Complément indispensable au TenantScope applicatif.
 */
final class RlsTenantTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    public function test_la_rls_filtre_meme_une_requete_brute_hors_eloquent(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->pourTenant($a, fn () => Client::factory()->count(2)->create());
        $this->pourTenant($b, fn () => Client::factory()->count(3)->create());

        // DB::table contourne totalement Eloquent : seule la RLS protège ici.
        $this->pourTenant($a, fn () => $this->assertSame(2, DB::table('clients')->count()));
        $this->pourTenant($b, fn () => $this->assertSame(3, DB::table('clients')->count()));
    }

    public function test_la_rls_bloque_une_ecriture_brute_vers_un_autre_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->expectException(QueryException::class);

        // Contexte A, mais on tente d'insérer une ligne du tenant B en brut.
        $this->pourTenant($a, function () use ($b): void {
            DB::table('clients')->insert([
                'id' => (string) Str::uuid7(),
                'tenant_id' => $b->id,
                'nom' => 'Insertion frauduleuse',
                'canaux' => '{}',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function test_la_rls_est_fail_closed_hors_contexte(): void
    {
        $a = Tenant::factory()->create();
        $this->pourTenant($a, fn () => Client::factory()->count(2)->create());

        // Hors contexte, GUC vide : aucune ligne visible, même en requête brute.
        $this->assertSame(0, DB::table('clients')->count());
    }

    /**
     * Invariant : toute table portant tenant_id doit avoir la RLS FORCÉE et au
     * moins une policy. Empêche qu'une future migration oublie RlsTenant::activer().
     * Exemption : « users » porte tenant_id mais n'est pas scopée (amorçage auth).
     */
    public function test_toute_table_avec_tenant_id_a_la_rls_forcee(): void
    {
        $exemptes = ['users'];

        $tables = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'tenant_id')
            ->pluck('table_name')
            ->unique()
            ->reject(fn (string $t): bool => in_array($t, $exemptes, true));

        $this->assertNotEmpty($tables, 'Aucune table scopée trouvée : le test ne garantirait rien.');

        foreach ($tables as $table) {
            $meta = DB::selectOne(
                'select relrowsecurity, relforcerowsecurity from pg_class where relname = ? and relnamespace = \'public\'::regnamespace',
                [$table],
            );

            $this->assertTrue((bool) $meta->relrowsecurity, "RLS non activée sur « {$table} ».");
            $this->assertTrue((bool) $meta->relforcerowsecurity, "FORCE RLS non activée sur « {$table} ».");

            $policies = DB::selectOne(
                'select count(*) as n from pg_policies where schemaname = \'public\' and tablename = ?',
                [$table],
            );

            $this->assertGreaterThan(0, (int) $policies->n, "Aucune policy RLS sur « {$table} ».");
        }
    }
}
