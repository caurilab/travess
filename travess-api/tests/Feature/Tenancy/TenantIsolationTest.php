<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Exceptions\TenantContextMissingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Étanchéité inter-tenant : l'invariant le plus critique du produit.
 *
 * Cette suite est permanente. Elle doit rester verte à chaque lot : toute
 * régression ici est une fuite de données entre sociétés clientes.
 */
final class TenantIsolationTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    public function test_les_lectures_sont_filtrees_par_tenant(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->pourTenant($a, fn () => Client::factory()->count(2)->create());
        $this->pourTenant($b, fn () => Client::factory()->count(3)->create());

        $this->pourTenant($a, fn () => $this->assertSame(2, Client::count()));
        $this->pourTenant($b, fn () => $this->assertSame(3, Client::count()));
    }

    public function test_la_creation_force_le_tenant_du_contexte(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        // On tente d'imposer le tenant B alors que le contexte est A.
        $client = $this->pourTenant(
            $a,
            fn () => Client::factory()->create(['tenant_id' => $b->id, 'nom' => 'Injecté']),
        );

        // Le tenant_id fourni par le client est ignoré : c'est celui du contexte.
        $this->assertSame($a->id, $client->tenant_id);
    }

    public function test_acces_a_un_id_d_un_autre_tenant_renvoie_null(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $clientDeB = $this->pourTenant($b, fn () => Client::factory()->create());

        // Depuis A, l'enregistrement de B est invisible (404, jamais 403 côté API).
        $this->pourTenant($a, fn () => $this->assertNull(Client::find($clientDeB->id)));
    }

    public function test_lecture_sans_contexte_echoue_fail_closed(): void
    {
        Tenant::factory()->create();

        $this->expectException(TenantContextMissingException::class);

        Client::count();
    }

    public function test_creation_sans_contexte_echoue_fail_closed(): void
    {
        $this->expectException(TenantContextMissingException::class);

        Client::factory()->create();
    }

    public function test_le_bypass_systeme_voit_tous_les_tenants(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $this->pourTenant($a, fn () => Client::factory()->count(2)->create());
        $this->pourTenant($b, fn () => Client::factory()->count(3)->create());

        $total = $this->contexteTenant()->runBypassed(fn () => Client::count());

        $this->assertSame(5, $total);
    }

    /**
     * Garde-fou anti-régression : tout modèle métier portant une colonne
     * tenant_id doit être auto-scopé (trait BelongsToTenant), à l'exception de
     * la liste blanche explicite (modèles d'amorçage de l'authentification).
     */
    public function test_tout_modele_avec_tenant_id_est_scope(): void
    {
        $exemptes = [
            User::class, // scoping explicite (forTenant + policy), cf. amorçage auth
        ];

        $fichiers = glob(app_path('Domains/*/Models/*.php')) ?: [];
        $verifies = 0;

        foreach ($fichiers as $fichier) {
            $classe = $this->classeDepuisChemin($fichier);

            if (! class_exists($classe)) {
                continue;
            }

            $reflection = new ReflectionClass($classe);

            if ($reflection->isAbstract() || ! $reflection->isSubclassOf(Model::class)) {
                continue;
            }

            /** @var Model $instance */
            $instance = new $classe;

            if (! Schema::hasColumn($instance->getTable(), 'tenant_id')) {
                continue;
            }

            $verifies++;

            if (in_array($classe, $exemptes, true)) {
                continue;
            }

            $this->assertContains(
                BelongsToTenant::class,
                class_uses_recursive($classe),
                "Le modèle {$classe} porte tenant_id mais n'utilise pas BelongsToTenant "
                .'(risque de fuite inter-tenant). L\'ajouter, ou l\'inscrire explicitement '
                .'dans la liste blanche avec justification.'
            );
        }

        // Au moins un modèle scopé doit exister, sinon le test ne garantit rien.
        $this->assertGreaterThan(0, $verifies);
    }

    private function classeDepuisChemin(string $chemin): string
    {
        $relatif = str_replace([app_path().'/', '.php', '/'], ['', '', '\\'], $chemin);

        return 'App\\'.$relatif;
    }
}
