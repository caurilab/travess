<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deux tenants de démonstration (A et B) avec chacun un gérant, un agent et
 * des clients. Support des tests d'étanchéité inter-tenant : ce qui appartient
 * à A ne doit jamais être visible depuis B, et réciproquement.
 */
final class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->creerTenant(
            nom: 'Transit Atlantique (A)',
            emailGerant: 'gerant@a.travess.test',
            emailAgent: 'agent@a.travess.test',
        );

        $this->creerTenant(
            nom: 'Corridor Sahel (B)',
            emailGerant: 'gerant@b.travess.test',
            emailAgent: 'agent@b.travess.test',
        );
    }

    private function creerTenant(string $nom, string $emailGerant, string $emailAgent): void
    {
        $tenant = Tenant::factory()->create(['nom' => $nom]);

        // Utilisateurs (non scopés : tenant_id explicite).
        User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Gerant)->create([
            'nom' => 'Gérant '.$nom,
            'email' => $emailGerant,
            'password' => Hash::make('motdepasse'),
        ]);

        User::factory()->pourTenant($tenant)->role(RoleUtilisateur::Agent)->create([
            'nom' => 'Agent '.$nom,
            'email' => $emailAgent,
            'password' => Hash::make('motdepasse'),
        ]);

        // Données scopées : nécessite un contexte tenant établi.
        $context = app(TenantContext::class);
        $context->set($tenant->id);

        try {
            Client::factory()->count(3)->create();
        } finally {
            $context->forget();
        }
    }
}
