<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Conteneurs\Enums\SourceSuiviTracking;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\SuiviTracking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuiviTracking>
 */
final class SuiviTrackingFactory extends Factory
{
    protected $model = SuiviTracking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conteneur_id' => Conteneur::factory(),
            'source' => fake()->randomElement(SourceSuiviTracking::cases()),
            'snapshot' => ['statut' => 'in_transit', 'events' => []],
            'statut_conteneur' => fake()->randomElement(['a_traiter', 'enleve', 'livre']),
            'emplacement' => fake()->city(),
            'eta_destination' => fake()->optional()->dateTimeBetween('now', '+3 weeks'),
            'navire_nom' => fake()->optional()->word(),
            'navire_imo' => (string) fake()->numerify('#######'),
            'prochain_poll_prevu' => fake()->dateTimeBetween('now', '+2 days'),
            'captured_at' => now(),
        ];
    }
}
