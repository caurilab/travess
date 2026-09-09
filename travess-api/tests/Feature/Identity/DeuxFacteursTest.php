<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Cycle de vie du 2FA TOTP : activation, confirmation, exigence du code au
 * login, et durcissements de l'audit (E-1, M-3).
 */
final class DeuxFacteursTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateurActif(): User
    {
        $tenant = Tenant::factory()->create();

        return User::factory()->pourTenant($tenant)->create([
            'email' => 'gerant@a.test',
            'password' => Hash::make('secret123'),
        ]);
    }

    /**
     * Active et confirme le 2FA de l'utilisateur (déjà authentifié par Sanctum).
     *
     * @return array{secret: string, codes: list<string>}
     */
    private function activerEtConfirmer(): array
    {
        $activation = $this->postJson('/api/v1/auth/2fa/activer')->assertOk();
        $secret = $activation->json('data.secret');
        $codes = $activation->json('data.codes_recuperation');

        $this->postJson('/api/v1/auth/2fa/confirmer', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ])->assertNoContent();

        return ['secret' => $secret, 'codes' => $codes];
    }

    public function test_activation_confirmation_puis_login_avec_code(): void
    {
        $user = $this->utilisateurActif();
        Sanctum::actingAs($user);

        ['secret' => $secret, 'codes' => $codes] = $this->activerEtConfirmer();
        $this->assertNotEmpty($secret);
        $this->assertNotEmpty($codes);

        // Login sans code → défi 2FA.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'gerant@a.test',
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('data.challenge', '2fa');

        // Login avec un code de récupération (facteur à usage unique).
        $this->postJson('/api/v1/auth/login', [
            'email' => 'gerant@a.test',
            'password' => 'secret123',
            'code' => $codes[0],
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        // Ce code de récupération est consommé : il ne fonctionne plus.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'gerant@a.test',
            'password' => 'secret123',
            'code' => $codes[0],
        ])->assertStatus(422);
    }

    public function test_activer_refuse_de_reexposer_un_2fa_deja_confirme(): void
    {
        $user = $this->utilisateurActif();
        Sanctum::actingAs($user);

        $this->activerEtConfirmer();

        // E-1 : sur un 2FA déjà actif, activer renvoie 409 et n'expose rien.
        $this->postJson('/api/v1/auth/2fa/activer')
            ->assertStatus(409)
            ->assertJsonMissingPath('data.secret')
            ->assertJsonMissingPath('data.codes_recuperation');
    }

    public function test_desactiver_exige_un_facteur_frais(): void
    {
        $user = $this->utilisateurActif();
        Sanctum::actingAs($user);

        ['codes' => $codes] = $this->activerEtConfirmer();

        // M-3 : sans code, la désactivation est refusée.
        $this->postJson('/api/v1/auth/2fa/desactiver')->assertStatus(422);

        // Avec un code de récupération valide, elle aboutit.
        $this->postJson('/api/v1/auth/2fa/desactiver', ['code' => $codes[0]])
            ->assertNoContent();

        $user->refresh();
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertNull($user->two_factor_secret);
    }
}
