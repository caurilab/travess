<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Enums\StatutEtape;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Models\Etape;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Etape>
 */
final class EtapeFactory extends Factory
{
    protected $model = Etape::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'ordre' => fake()->numberBetween(1, 10),
            'libelle' => fake()->randomElement([
                'Réception BL', 'Déclaration douane', 'Paiement droits',
                'Enlèvement conteneur', 'Livraison client',
            ]),
            'sla_jours' => fake()->numberBetween(1, 15),
            'date_prevue' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'date_reelle' => null,
            'statut' => fake()->randomElement(StatutEtape::cases()),
            'responsable_id' => null,
        ];
    }
}
