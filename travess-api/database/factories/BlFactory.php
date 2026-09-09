<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bl>
 */
final class BlFactory extends Factory
{
    protected $model = Bl::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'numero' => strtoupper(fake()->bothify('??######')),
            'armateur_id' => Armateur::factory(),
            'navire_nom' => fake()->optional()->randomElement(['Ever Given', 'MSC Gülsün', 'CMA CGM Marco Polo']),
            'navire_imo' => (string) fake()->numerify('#######'), // clé fiable navire
        ];
    }
}
