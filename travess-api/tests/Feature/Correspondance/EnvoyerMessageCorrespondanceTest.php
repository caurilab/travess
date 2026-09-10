<?php

declare(strict_types=1);

namespace Tests\Feature\Correspondance;

use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Jobs\EnvoyerMessageCorrespondance;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Messagerie\Adapters\ExpediteurFactice;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Messagerie\Mail\CorrespondanceMail;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Job d'envoi de la correspondance (principe n°4, dans le contexte tenant).
 * En driver « reel » l'e-mail part réellement et le message passe à « envoyé » ;
 * le job est idempotent (un message déjà « envoyé » n'est pas ré-émis). En
 * driver « factice » (défaut) l'envoi est journalisé mais le message reste « en
 * file » (l'expéditeur factice retourne « en file »).
 */
final class EnvoyerMessageCorrespondanceTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * Crée un message « en file » dans un tenant frais.
     *
     * @return array{Tenant, string}
     */
    private function messageEnFile(string $adresse = 'ops@maersk.test'): array
    {
        $tenant = Tenant::factory()->create();

        $id = $this->pourTenant($tenant, function () use ($adresse): string {
            $client = Client::factory()->create();
            $dossier = Dossier::factory()->create(['client_id' => $client->id]);

            return Message::factory()->create([
                'dossier_id' => $dossier->id,
                'canal' => CanalMessage::Email->value,
                'destinataire_adresse' => $adresse,
                'objet' => 'Relance surestaries — dossier MAEU123456789',
                'corps' => "Bonjour,\n\nMerci d'accélérer.\n\nCordialement",
                'statut' => StatutMessage::EnFile->value,
            ])->id;
        });

        return [$tenant, $id];
    }

    public function test_driver_reel_envoie_l_email_et_marque_envoye(): void
    {
        config(['messagerie.driver' => 'reel']);
        Mail::fake();

        [$tenant, $id] = $this->messageEnFile('ops@maersk.test');

        EnvoyerMessageCorrespondance::dispatch($tenant->id, $id);

        Mail::assertSent(CorrespondanceMail::class, function (CorrespondanceMail $mail): bool {
            return $mail->hasTo('ops@maersk.test')
                && $mail->objet === 'Relance surestaries — dossier MAEU123456789';
        });

        $message = $this->pourTenant($tenant, fn (): Message => Message::findOrFail($id));
        $this->assertSame(StatutMessage::Envoye, $message->statut);
        $this->assertNotNull($message->envoye_at);
    }

    public function test_rejouer_un_message_deja_envoye_ne_renvoie_pas(): void
    {
        config(['messagerie.driver' => 'reel']);
        Mail::fake();

        [$tenant, $id] = $this->messageEnFile();
        // Le message a déjà été envoyé (issue d'un premier passage).
        $this->pourTenant($tenant, function () use ($id): void {
            Message::findOrFail($id)->forceFill(['statut' => StatutMessage::Envoye->value])->save();
        });

        EnvoyerMessageCorrespondance::dispatch($tenant->id, $id);

        // Garde d'idempotence : rien n'est ré-émis, le statut ne bouge pas.
        Mail::assertNothingSent();
        $message = $this->pourTenant($tenant, fn (): Message => Message::findOrFail($id));
        $this->assertSame(StatutMessage::Envoye, $message->statut);
    }

    public function test_driver_factice_journalise_mais_laisse_en_file(): void
    {
        // Driver factice = défaut (aucune surcharge de config).
        app(ExpediteurFactice::class)->reset();

        [$tenant, $id] = $this->messageEnFile('ops@maersk.test');

        EnvoyerMessageCorrespondance::dispatch($tenant->id, $id);

        // L'expéditeur factice a bien journalisé l'envoi sur le canal e-mail…
        $envoi = app(ExpediteurFactice::class)->dernier();
        $this->assertNotNull($envoi);
        $this->assertSame('email', $envoi['canal']);
        $this->assertSame('correspondance_libre', $envoi['gabarit']);

        // … mais le message reste « en file » (factice retourne « en file »).
        $message = $this->pourTenant($tenant, fn (): Message => Message::findOrFail($id));
        $this->assertSame(StatutMessage::EnFile, $message->statut);
        $this->assertNull($message->envoye_at);
    }
}
