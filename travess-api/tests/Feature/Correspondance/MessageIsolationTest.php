<?php

declare(strict_types=1);

namespace Tests\Feature\Correspondance;

use App\Domains\Correspondance\Models\Message;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\InteragitAvecLeTenant;
use Tests\TestCase;

/**
 * Isolation multi-tenant de la correspondance (principe n°3) : un acteur d'un
 * tenant ne voit jamais le message d'un autre. La RLS masque la ligne au
 * route-model binding → 404 (jamais 403, convention API du projet).
 */
final class MessageIsolationTest extends TestCase
{
    use InteragitAvecLeTenant;
    use RefreshDatabase;

    public function test_message_du_tenant_visible_par_son_acteur(): void
    {
        $tenant = Tenant::factory()->create();
        $gerant = User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create();

        $messageId = $this->pourTenant($tenant, function (): string {
            $client = Client::factory()->create();
            $dossier = Dossier::factory()->create(['client_id' => $client->id]);

            return Message::factory()->create(['dossier_id' => $dossier->id])->id;
        });

        Sanctum::actingAs($gerant);
        $this->getJson("/api/v1/messages/{$messageId}")
            ->assertOk()
            ->assertJsonPath('data.id', $messageId);
    }

    public function test_message_d_un_autre_tenant_est_introuvable(): void
    {
        // Message créé chez le tenant B.
        $tenantB = Tenant::factory()->create();
        $messageIdB = $this->pourTenant($tenantB, function (): string {
            $client = Client::factory()->create();
            $dossier = Dossier::factory()->create(['client_id' => $client->id]);

            return Message::factory()->create(['dossier_id' => $dossier->id])->id;
        });

        // Un gérant du tenant A tente de le lire → 404 (RLS, pas 403).
        $tenantA = Tenant::factory()->create();
        Sanctum::actingAs(User::factory()->pourTenant($tenantA)->role(RoleUtilisateur::Gerant)->create());

        $this->getJson("/api/v1/messages/{$messageIdB}")->assertNotFound();
    }
}
