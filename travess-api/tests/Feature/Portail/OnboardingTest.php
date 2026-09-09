<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Messagerie\Adapters\ExpediteurFactice;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Enums\StatutInvitation;
use App\Domains\Portail\Jobs\EnvoyerInvitation;
use App\Domains\Portail\Models\InvitationPortail;
use App\Domains\Tenancy\Enums\TypeTenant;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Scopes\TenantScope;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Onboarding portail de bout en bout (ADR-013, 7.2a) SANS fournisseur réel :
 * émission (transitaire) → réclamation publique (OTP factice) → confirmation
 * (provisionnement compte + activation de l'accès). Puis le client accède à sa
 * vue limitée avec le jeton émis.
 */
final class OnboardingTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    private Tenant $transitaire;

    private User $gerant;

    private string $dossierId;

    private const TELEPHONE = '+22890123456';

    protected function setUp(): void
    {
        parent::setUp();
        config(['messagerie.otp.code_factice' => '123456']);

        $this->transitaire = Tenant::factory()->create();
        $this->gerant = User::factory()->pourTenant($this->transitaire)->role(RoleUtilisateur::Gerant)->create();
        $this->pourTenant($this->transitaire, function (): void {
            $client = Client::factory()->create(['nom' => 'Import Sahel SARL']);
            $dossier = Dossier::factory()->create(['client_id' => $client->id]);
            $bl = Bl::factory()->create(['dossier_id' => $dossier->id, 'numero' => 'BL-ONB', 'armateur_id' => Armateur::factory()->create()->id]);
            $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id, 'numero' => 'MSCU7390252']);
            SuiviTracking::factory()->create(['conteneur_id' => $conteneur->id]);
            $this->dossierId = $dossier->id;
        });
    }

    private function emettre(): string
    {
        Sanctum::actingAs($this->gerant);

        $lien = $this->postJson("/api/v1/dossiers/{$this->dossierId}/invitations", [
            'canal' => 'whatsapp',
            'destinataire' => self::TELEPHONE,
        ])->assertStatus(202)->json('lien');

        // Repartir « déconnecté » pour le parcours public.
        $this->app['auth']->forgetGuards();

        return $lien;
    }

    public function test_flux_complet_emission_reclamation_confirmation(): void
    {
        $lien = $this->emettre();

        // L'invitation part bien sur WhatsApp via l'expéditeur factice.
        $envoi = app(ExpediteurFactice::class)->dernier();
        $this->assertNotNull($envoi);
        $this->assertSame('whatsapp', $envoi['canal']);

        // Réclamation publique (lien signé) → OTP émis.
        $this->getJson($lien)
            ->assertOk()
            ->assertJsonPath('canal', 'whatsapp')
            ->assertJsonStructure(['destination_masquee', 'otp_expire_at']);

        // Confirmation avec le code OTP factice.
        $tokenPath = $this->cheminToken($lien);
        $reponse = $this->postJson("/api/v1/portail/invitations/{$tokenPath}/confirmer", [
            'code_otp' => '123456',
        ])->assertStatus(201)->assertJsonStructure(['token', 'user_id']);

        // Un compte client (tenant type=client) et un octroi actif ont été créés.
        $userId = $reponse->json('user_id');
        $user = User::findOrFail($userId);
        $this->assertSame(RoleUtilisateur::Client, $user->role);
        $this->assertSame(self::TELEPHONE, $user->telephone);
        $this->assertSame(TypeTenant::Client, $user->tenant->type);

        $this->pourTenant($this->transitaire, function () use ($userId): void {
            $this->assertDatabaseHas('acces_dossier', [
                'dossier_id' => $this->dossierId,
                'beneficiaire_user_id' => $userId,
                'statut' => StatutAcces::Actif->value,
            ]);
        });

        // Le jeton émis donne accès à la vue limitée.
        $this->withToken($reponse->json('token'))
            ->getJson('/api/v1/portail/dossiers')
            ->assertOk()
            ->assertJsonPath('data.0.bls.0.numero', 'BL-ONB');
    }

    public function test_invitation_consommee_n_est_pas_rejouable(): void
    {
        $lien = $this->emettre();
        $token = $this->cheminToken($lien);

        $this->getJson($lien)->assertOk();
        $this->postJson("/api/v1/portail/invitations/{$token}/confirmer", ['code_otp' => '123456'])->assertStatus(201);

        // Rejeu : l'invitation est consommée → réclamation/confirmation refusées.
        $this->getJson($lien)->assertNotFound();
        $this->postJson("/api/v1/portail/invitations/{$token}/confirmer", ['code_otp' => '123456'])->assertNotFound();
    }

    public function test_mauvais_otp_est_refuse(): void
    {
        $lien = $this->emettre();
        $token = $this->cheminToken($lien);
        $this->getJson($lien)->assertOk();

        $this->postJson("/api/v1/portail/invitations/{$token}/confirmer", ['code_otp' => '000000'])
            ->assertStatus(422);

        // L'invitation reste émise (non consommée).
        $this->pourTenant($this->transitaire, fn () => $this->assertSame(
            StatutInvitation::Emise,
            InvitationPortail::withoutGlobalScope(TenantScope::class)->firstOrFail()->statut,
        ));
    }

    public function test_lien_non_signe_est_refuse(): void
    {
        $this->emettre();
        $token = $this->pourTenant($this->transitaire, fn (): string => InvitationPortail::withoutGlobalScope(TenantScope::class)->firstOrFail()->id);

        // Sans signature valide, la route est rejetée (403).
        $this->getJson('/api/v1/portail/invitations/nimportequoi')->assertForbidden();
    }

    public function test_numero_deja_transitaire_est_refuse(): void
    {
        // Un compte non-client possède déjà ce numéro → non-cumul strict.
        $this->pourTenant($this->transitaire, fn () => $this->gerant->forceFill(['telephone' => self::TELEPHONE])->save());

        $lien = $this->emettre();
        $token = $this->cheminToken($lien);
        $this->getJson($lien)->assertOk();

        $this->postJson("/api/v1/portail/invitations/{$token}/confirmer", ['code_otp' => '123456'])
            ->assertStatus(409);
    }

    public function test_otp_verrouille_persiste_malgre_reemission(): void
    {
        $lien = $this->emettre();
        $token = $this->cheminToken($lien);
        $this->getJson($lien)->assertOk();

        // 5 mauvais codes → verrouillage (le compteur n'est pas réinitialisé).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson("/api/v1/portail/invitations/{$token}/confirmer", ['code_otp' => '000000'])
                ->assertStatus(422);
        }

        // Une réémission est refusée (429), et le bon code ne passe plus.
        $this->getJson($lien)->assertStatus(429);
        $this->postJson("/api/v1/portail/invitations/{$token}/confirmer", ['code_otp' => '123456'])
            ->assertStatus(422);
    }

    public function test_plafond_d_emission_par_tenant(): void
    {
        config(['messagerie.invitation.emission_max_par_tenant_heure' => 1]);
        Sanctum::actingAs($this->gerant);

        $charge = ['canal' => 'whatsapp', 'destinataire' => self::TELEPHONE];
        $this->postJson("/api/v1/dossiers/{$this->dossierId}/invitations", $charge)->assertStatus(202);
        $this->postJson("/api/v1/dossiers/{$this->dossierId}/invitations", $charge)->assertStatus(429);
    }

    public function test_le_job_d_envoi_chiffre_sa_charge(): void
    {
        // La charge (lien = token secret, destinataire = PII) ne doit jamais être
        // sérialisée en clair dans la file / failed_jobs.
        $job = new EnvoyerInvitation(
            $this->transitaire->id, 'whatsapp', self::TELEPHONE, 'https://x/portail', 'REF-1',
        );

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
    }

    private function cheminToken(string $lien): string
    {
        $chemin = parse_url($lien, PHP_URL_PATH);
        $segments = explode('/', (string) $chemin);
        $index = array_search('invitations', $segments, true);

        return $segments[$index + 1];
    }
}
