<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Domains\Consommation\Models\ConsommationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsommationService>
 */
final class ConsommationServiceFactory extends Factory
{
    protected $model = ConsommationService::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service' => fake()->randomElement(ServiceConsomme::cases()),
            'periode' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m'),
            'quantite' => fake()->numberBetween(0, 5000),
        ];
    }
}
