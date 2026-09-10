<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Alertes\Models\Alerte;
use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Database\Seeder;

/**
 * Jeu de démonstration (dev uniquement) : un transitaire, un gérant, et des
 * franchises « en train de brûler » pour alimenter le tableau de bord.
 *
 *   php artisan db:seed --class=Database\\Seeders\\DemoSeeder
 *   Connexion : demo@travess.ci / password
 */
final class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['nom' => 'Démo Transit CI'],
            ['type' => 'transitaire', 'quota_ia_mensuel' => 100, 'quota_tracking_mensuel' => 1000],
        );

        // Active l'ingestion IA (opt-in) pour la démo : l'onglet Documents peut
        // lancer l'extraction (driver « factice » en dev, sans clé ni réseau).
        if (($tenant->parametres['ia_activee'] ?? false) !== true) {
            $tenant->forceFill(['parametres' => [...($tenant->parametres ?? []), 'ia_activee' => true]])->save();
        }

        if (User::where('email', 'demo@travess.ci')->doesntExist()) {
            $user = new User([
                'nom' => 'Awa Koné',
                'email' => 'demo@travess.ci',
                'role' => RoleUtilisateur::Gerant->value,
                'password' => 'password',
            ]);
            $user->forceFill(['tenant_id' => $tenant->id])->save();
        }

        app(TenantContext::class)->pour($tenant->id, function (): void {
            if (Franchise::query()->exists()) {
                $this->semerEtapes();
                $this->semerAlertes();

                return;
            }

            $client = Client::factory()->create(['nom' => 'Import Sahel SARL']);
            $armateur = Armateur::factory()->create(['nom' => 'Maersk', 'email' => 'booking.ci@maersk.example']);
            $dossier = Dossier::factory()->create([
                'client_id' => $client->id,
                'sens' => 'import',
                'reference' => 'IMP-2026-0001',
            ]);
            $bl = Bl::factory()->create([
                'dossier_id' => $dossier->id,
                'armateur_id' => $armateur->id,
                'numero' => 'MAEU-778812',
            ]);

            $lots = [
                ['MSCU7390252', 'surestaries', 1, 850_000, 340_000],
                ['MAEU6123458', 'detention', 2, 620_000, 180_000],
                ['TGHU8563203', 'surestaries', 0, 1_250_000, 500_000],
                ['HLXU1234561', 'surestaries', 3, 210_000, 0],
                ['CMAU2000004', 'detention', 1, 430_000, 90_000],
            ];

            foreach ($lots as [$numero, $type, $joursRestants, $menacant, $encours]) {
                $conteneur = Conteneur::factory()->create(['bl_id' => $bl->id, 'numero' => $numero]);
                Franchise::factory()->create([
                    'conteneur_id' => $conteneur->id,
                    'type' => $type,
                    'actif' => true,
                    'jours_francs' => 7,
                    'date_debut' => now()->subDays(10),
                    'date_fin_franchise' => now()->addDays($joursRestants)->toDateString(),
                    'montant_en_cours' => $encours,
                    'montant_menacant' => $menacant,
                ]);
            }

            $this->semerAlertes();
        });
    }

    private function semerEtapes(): void
    {
        if (\App\Domains\Dossiers\Models\Etape::query()->exists()) {
            return;
        }

        $dossier = Dossier::query()->first();
        if ($dossier === null) {
            return;
        }

        $etapes = [
            ['Ouverture du dossier', 'fait', -8],
            ['Réception documents', 'fait', -5],
            ['Déclaration douane', 'en_cours', 1],
            ['Enlèvement conteneurs', 'a_faire', 4],
            ['Livraison client', 'a_faire', 8],
        ];

        foreach ($etapes as $i => [$libelle, $statut, $dans]) {
            \App\Domains\Dossiers\Models\Etape::query()->create([
                'dossier_id' => $dossier->id,
                'ordre' => $i + 1,
                'libelle' => $libelle,
                'sla_jours' => 3,
                'date_prevue' => now()->addDays($dans)->toDateString(),
                'date_reelle' => $statut === 'fait' ? now()->addDays($dans)->toDateString() : null,
                'statut' => $statut,
            ]);
        }
    }

    private function semerAlertes(): void
    {
        if (Alerte::query()->exists()) {
            return;
        }

        $conteneurs = Conteneur::query()->with('bl')->limit(3)->get();
        $types = ['surestaries_j0', 'surestaries_j1', 'detention_j3'];
        $statuts = ['ouverte', 'ouverte', 'vue'];

        foreach ($conteneurs as $i => $conteneur) {
            Alerte::query()->create([
                'dossier_id' => $conteneur->bl->dossier_id,
                'conteneur_id' => $conteneur->id,
                'type' => $types[$i] ?? 'surestaries_j1',
                'montant_menacant' => 850_000 - $i * 120_000,
                'statut' => $statuts[$i] ?? 'ouverte',
                'canaux_envoyes' => ['email'],
            ]);
        }
    }
}
