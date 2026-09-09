<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Alertes\Enums\StatutAlerte;
use App\Domains\Alertes\Enums\TypeAlerte;
use App\Domains\Alertes\Models\Alerte;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alerte>
 */
final class AlerteFactory extends Factory
{
    protected $model = Alerte::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'conteneur_id' => null,
            'type' => fake()->randomElement(TypeAlerte::cases()),
            'montant_menacant' => fake()->optional()->randomFloat(2, 10000, 500000),
            'statut' => fake()->randomElement(StatutAlerte::cases()),
            'canaux_envoyes' => [],
        ];
    }
}
