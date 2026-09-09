<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Finances\Models\Charge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Charge>
 */
final class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'libelle' => fake()->randomElement(['Droits de douane', 'Manutention', 'Surestaries', 'Transport terrestre']),
            'montant' => fake()->randomFloat(2, 50000, 2000000),
            'avancee_pour_client' => fake()->boolean(),
        ];
    }
}
