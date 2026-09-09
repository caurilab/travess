<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Documents\Enums\StatutExtraction;
use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\ExtractionIa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtractionIa>
 */
final class ExtractionIaFactory extends Factory
{
    protected $model = ExtractionIa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'statut' => fake()->randomElement(StatutExtraction::cases()),
            'champs' => [
                'numero_bl' => ['valeur' => fake()->bothify('??######'), 'confiance' => 0.92, 'zone_source' => 'p1'],
            ],
            'corrections' => [],
            'valide_par' => null,
            'valide_at' => null,
            'cout_unite' => fake()->randomFloat(2, 0.01, 0.5),
        ];
    }
}
