<?php

declare(strict_types=1);

namespace Tests\Feature\Correspondance;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Correspondance\Enums\DirectionMessage;
use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * API de correspondance armateur (docs/10 §12) : brouillon pré-rempli, création
 * + mise en file (202), fil filtrable, et matrice d'autorisation (lecture pour
 * tous les rôles agence, envoi réservé gérant/agent). Driver messagerie factice
 * par défaut : l'envoi dispatché (sync/afterCommit) laisse le message « en file ».
 */
final class MessageApiTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    /**
     * Contexte complet : tenant, gérant acté, et un dossier avec un BL, un
     * conteneur, une franchise active menaçante, plus un armateur (avec e-mail).
     *
     * @return array{tenant: Tenant, gerant: User, dossier: Dossier, armateur: Armateur}
     */
    private function contexte(): array
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();

        $donnees = $this->pourTenant($tenant, function (): array {
            $client = Client::factory()->create();
            $armateur = Armateur::factory()->create(['nom' => 'Maersk', 'email' => 'ops@maersk.test']);
            $dossier = Dossier::factory()->create(['client_id' => $client->id, 'reference' => 'IMP-2026-0001']);
            $bl = Bl::factory()->create([
                'dossier_id' => $dossier->id,
                'armateur_id' => $armateur->id,
                'numero' => 'MAEU123456789',
            ]);
            $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id]);
            Franchise::factory()->create([
                'conteneur_id' => $conteneur->id,
                'type' => TypeFranchise::Surestaries->value,
                'date_fin_franchise' => '2026-01-20',
                'montant_menacant' => 60000,
                'actif' => true,
            ]);

            return ['dossier' => $dossier, 'armateur' => $armateur];
        });

        return [
            'tenant' => $tenant,
            'gerant' => $gerant,
            'dossier' => $donnees['dossier'],
            'armateur' => $donnees['armateur'],
        ];
    }

    public function test_brouillon_relance_surestaries_pre_remplit_objet_et_corps(): void
    {
        $c = $this->contexte();
        Sanctum::actingAs($c['gerant']);

        $reponse = $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages/brouillon", [
            'type_demande' => 'relance_surestaries',
        ]);

        $reponse->assertOk()
            ->assertJsonStructure(['data' => ['objet', 'corps', 'type_demande']])
            ->assertJsonPath('data.type_demande', 'relance_surestaries');

        $corps = $reponse->json('data.corps');
        $this->assertStringContainsString('MAEU123456789', $corps); // numéro de BL
        $this->assertStringContainsString('60 000 FCFA', $corps);    // total de surestaries menaçant
        $this->assertStringContainsString('MAEU123456789', $reponse->json('data.objet'));
    }

    public function test_creation_met_en_file_le_message_sortant(): void
    {
        $c = $this->contexte();
        Sanctum::actingAs($c['gerant']);

        $reponse = $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages", [
            'canal' => 'email',
            'armateur_id' => $c['armateur']->id,
            'objet' => 'Relance surestaries — dossier MAEU123456789',
            'corps' => "Bonjour,\n\nMerci d'accélérer.\n\nCordialement",
        ]);

        $reponse->assertStatus(202)
            ->assertJsonPath('data.statut', 'en_file')
            ->assertJsonPath('data.direction', 'sortant')
            ->assertJsonPath('data.canal', 'email');

        $id = $reponse->json('data.id');
        $message = $this->pourTenant($c['tenant'], fn (): Message => Message::findOrFail($id));

        // Statut « en file », direction sortante, auteur = utilisateur courant.
        $this->assertSame(StatutMessage::EnFile, $message->statut);
        $this->assertSame(DirectionMessage::Sortant, $message->direction);
        $this->assertSame($c['gerant']->id, $message->auteur_id);
        // destinataire_adresse absent → retombe sur l'e-mail de l'armateur.
        $this->assertSame('ops@maersk.test', $message->destinataire_adresse);
    }

    public function test_creation_sans_adresse_ni_email_armateur_est_rejetee_422(): void
    {
        $c = $this->contexte();
        // Armateur sans e-mail dans le même tenant.
        $sansEmail = $this->pourTenant($c['tenant'], fn (): Armateur => Armateur::factory()->create(['email' => null]));
        Sanctum::actingAs($c['gerant']);

        $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages", [
            'canal' => 'email',
            'armateur_id' => $sansEmail->id,
            'objet' => 'Demande',
            'corps' => 'Bonjour',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('destinataire_adresse');

        // Rien n'a été persisté (règle métier bloque avant écriture).
        $this->pourTenant($c['tenant'], fn () => $this->assertSame(0, Message::count()));
    }

    public function test_adresse_libre_ignoree_quand_un_armateur_est_designe(): void
    {
        // B1 : on ne peut pas détourner une demande armateur vers une autre adresse.
        $c = $this->contexte();
        Sanctum::actingAs($c['gerant']);

        $reponse = $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages", [
            'canal' => 'email',
            'armateur_id' => $c['armateur']->id,
            'destinataire_adresse' => 'exfiltration@evil.test',
            'objet' => 'Demande',
            'corps' => 'Bonjour',
        ])->assertStatus(202);

        // L'adresse libre est ignorée : envoi à l'e-mail de carnet de l'armateur.
        $this->assertSame('ops@maersk.test', $reponse->json('data.destinataire_adresse'));
    }

    public function test_un_agent_ne_peut_pas_adresser_hors_carnet(): void
    {
        // B1 : sans armateur, l'adresse libre est réservée au gérant.
        $c = $this->contexte();
        $agent = User::factory()->pourTenant($c['tenant'])->role(RoleUtilisateur::Agent)->create();
        Sanctum::actingAs($agent);

        $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages", [
            'canal' => 'email',
            'destinataire_adresse' => 'agent-libre@ailleurs.test',
            'objet' => 'Demande',
            'corps' => 'Bonjour',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('destinataire_adresse');

        $this->pourTenant($c['tenant'], fn () => $this->assertSame(0, Message::count()));
    }

    public function test_liste_filtrable_triee_et_paginee(): void
    {
        $c = $this->contexte();

        $this->pourTenant($c['tenant'], function () use ($c): void {
            Message::factory()->create([
                'dossier_id' => $c['dossier']->id,
                'direction' => DirectionMessage::Sortant->value,
                'canal' => CanalMessage::Email->value,
                'type_demande' => TypeDemande::RelanceSurestaries->value,
            ]);
            Message::factory()->create([
                'dossier_id' => $c['dossier']->id,
                'direction' => DirectionMessage::Entrant->value,
                'canal' => CanalMessage::Whatsapp->value,
                'type_demande' => TypeDemande::Reclamation->value,
            ]);
        });

        Sanctum::actingAs($c['gerant']);

        $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'total']]);

        // Filtre par direction.
        $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages?filter[direction]=entrant")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.direction', 'entrant');

        // Filtre par canal.
        $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages?filter[canal]=email")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.canal', 'email');

        // Filtre par type de demande.
        $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages?filter[type_demande]=reclamation")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type_demande', 'reclamation');
    }

    public function test_liste_triee_par_date_decroissante_par_defaut(): void
    {
        $c = $this->contexte();

        [$ancienId, $recentId] = $this->pourTenant($c['tenant'], function () use ($c): array {
            $ancien = Message::factory()->create([
                'dossier_id' => $c['dossier']->id,
                'created_at' => now()->subDay(),
            ]);
            $recent = Message::factory()->create([
                'dossier_id' => $c['dossier']->id,
                'created_at' => now(),
            ]);

            return [$ancien->id, $recent->id];
        });

        Sanctum::actingAs($c['gerant']);

        $ids = $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages")
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$recentId, $ancienId], $ids); // -created_at
    }

    public function test_le_comptable_lit_mais_ne_peut_pas_envoyer(): void
    {
        $c = $this->contexte();
        $comptable = User::factory()->pourTenant($c['tenant'])->role(RoleUtilisateur::Comptable)->create();
        Sanctum::actingAs($comptable);

        // Lecture autorisée : fil et brouillon.
        $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages")->assertOk();
        $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages/brouillon", [
            'type_demande' => 'relance_surestaries',
        ])->assertOk();

        // Envoi refusé (réservé gérant/agent).
        $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages", [
            'canal' => 'email',
            'armateur_id' => $c['armateur']->id,
            'objet' => 'Relance',
            'corps' => 'Bonjour',
        ])->assertForbidden();
    }

    public function test_un_role_sans_lecture_est_refuse(): void
    {
        $c = $this->contexte();
        $chauffeur = User::factory()->pourTenant($c['tenant'])->role(RoleUtilisateur::Chauffeur)->create();
        Sanctum::actingAs($chauffeur);

        $this->getJson("/api/v1/dossiers/{$c['dossier']->id}/messages")->assertForbidden();
        $this->postJson("/api/v1/dossiers/{$c['dossier']->id}/messages/brouillon", [
            'type_demande' => 'relance_surestaries',
        ])->assertForbidden();
    }
}
