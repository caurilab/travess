<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Models\Franchise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Franchise>
 *
 * Les champs calculés sont ici initialisés à des valeurs neutres (dans la vraie
 * vie ils sont recalculés par shared-core).
 */
final class FranchiseFactory extends Factory
{
    protected $model = Franchise::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conteneur_id' => Conteneur::factory(),
            'type' => fake()->randomElement(TypeFranchise::cases()),
            'date_debut' => fake()->dateTimeBetween('-1 month', 'now'),
            'jours_francs' => fake()->numberBetween(3, 14),
            'date_fin_franchise' => null,
            'montant_en_cours' => 0,
            'montant_menacant' => 0,
            'actif' => false,
        ];
    }
}
