<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(string $email = 'agent@a.test', string $motDePasse = 'secret123'): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->pourTenant($tenant)->create([
            'email' => $email,
            'password' => Hash::make($motDePasse),
        ]);
    }

    public function test_login_renvoie_un_jeton(): void
    {
        $this->utilisateur();

        $reponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'agent@a.test',
            'password' => 'secret123',
        ]);

        $reponse->assertOk();
        $this->assertNotEmpty($reponse->json('data.token'));
    }

    public function test_login_rejette_un_mauvais_mot_de_passe_avec_un_message_generique(): void
    {
        $this->utilisateur();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'agent@a.test',
            'password' => 'mauvais',
        ])->assertStatus(422)->assertJsonValidationErrorFor('email');
    }

    public function test_login_ne_revele_pas_l_existence_d_un_email(): void
    {
        $inconnu = $this->postJson('/api/v1/auth/login', [
            'email' => 'inconnu@nulle-part.test',
            'password' => 'x',
        ]);

        // Même statut et même champ d'erreur qu'un mot de passe erroné.
        $inconnu->assertStatus(422)->assertJsonValidationErrorFor('email');
    }

    public function test_me_renvoie_utilisateur_tenant_et_permissions(): void
    {
        $user = $this->utilisateur();
        $token = $this->jeton();

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'agent@a.test')
            ->assertJsonPath('data.tenant.id', $user->tenant_id)
            ->assertJsonStructure(['data' => ['user', 'tenant', 'permissions']]);
    }

    public function test_me_exige_une_authentification(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_le_garde_fou_rejette_un_tenant_id_en_parametre(): void
    {
        $this->utilisateur();
        $token = $this->jeton();

        $this->withToken($token)->getJson('/api/v1/auth/me?tenant_id=autre')
            ->assertStatus(422);
    }

    public function test_logout_revoque_le_jeton(): void
    {
        $this->utilisateur();
        $token = $this->jeton();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        // Le jeton est bien révoqué en base.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // En test, le guard d'auth est réutilisé entre requêtes (en HTTP réel
        // chaque requête est neuve) : on le réinitialise pour re-résoudre.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_refresh_fait_tourner_le_jeton(): void
    {
        $this->utilisateur();
        $ancien = $this->jeton();

        $nouveau = $this->withToken($ancien)->postJson('/api/v1/auth/refresh')
            ->assertOk()->json('data.token');

        $this->app['auth']->forgetGuards();
        $this->withToken($ancien)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($nouveau)->getJson('/api/v1/auth/me')->assertOk();
    }

    private function jeton(string $email = 'agent@a.test', string $motDePasse = 'secret123'): string
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $motDePasse,
        ])->json('data.token');
    }
}
