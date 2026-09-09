<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Finances\Models\Encaissement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encaissement>
 */
final class EncaissementFactory extends Factory
{
    protected $model = Encaissement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'montant' => fake()->randomFloat(2, 50000, 2000000),
            'date' => fake()->dateTimeBetween('-2 months', 'now'),
            'rapproche_charge_id' => null,
        ];
    }
}
