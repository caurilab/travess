<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Finances\Models\Honoraire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Honoraire>
 */
final class HonoraireFactory extends Factory
{
    protected $model = Honoraire::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'montant' => fake()->randomFloat(2, 25000, 500000),
            'numero_facture' => 'FAC-'.fake()->unique()->numerify('######'),
            'pdf_chemin' => 'factures/'.fake()->uuid().'.pdf',
        ];
    }
}
