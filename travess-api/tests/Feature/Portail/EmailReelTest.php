<?php

declare(strict_types=1);

namespace Tests\Feature\Portail;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Messagerie\Mail\InvitationMail;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Expéditeur e-mail réel (ADR-013, 7.2b) : en driver « reel », une invitation
 * par e-mail part réellement (via le mailer), isolée derrière l'interface. Le
 * reste du flux reste testable en factice (défaut).
 */
final class EmailReelTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    public function test_invitation_email_reelle_est_envoyee_via_le_mailer(): void
    {
        config(['messagerie.driver' => 'reel']);
        Mail::fake();

        $transitaire = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($transitaire)->role(RoleUtilisateur::Gerant)->create();

        $dossierId = $this->pourTenant($transitaire, function (): string {
            $client = Client::factory()->create();

            return Dossier::factory()->create(['client_id' => $client->id])->id;
        });

        Sanctum::actingAs($gerant);
        $this->postJson("/api/v1/dossiers/{$dossierId}/invitations", [
            'canal' => 'email',
            'destinataire' => 'client@example.test',
        ])->assertStatus(202);

        // Le job EnvoyerInvitation (sync/afterCommit) a fait partir l'e-mail réel.
        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail): bool {
            return $mail->hasTo('client@example.test') && str_contains($mail->lien, '/portail/invitations/');
        });
    }
}
