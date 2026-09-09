<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Paiements\Enums\CiblePaiement;
use App\Domains\Paiements\Enums\OperateurPaiement;
use App\Domains\Paiements\Enums\StatutPaiement;
use App\Domains\Paiements\Models\Paiement;
use App\Domains\Tenancy\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
final class PaiementFactory extends Factory
{
    protected $model = Paiement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'client_id' => Client::factory(),
            'montant' => fake()->randomFloat(2, 10000, 1000000),
            'cible' => fake()->randomElement(CiblePaiement::cases()),
            'operateur' => fake()->randomElement(OperateurPaiement::cases()),
            'statut' => fake()->randomElement(StatutPaiement::cases()),
            'ref_agregateur' => fake()->optional()->bothify('AGG-########'),
            'commission_travess' => fake()->randomFloat(2, 100, 5000),
            'recu_chemin' => null,
        ];
    }
}
