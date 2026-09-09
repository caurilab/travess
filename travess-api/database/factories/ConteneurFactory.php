<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Conteneurs\Enums\SourceNumeroConteneur;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeConteneur;
use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Conteneurs\Models\Conteneur;
use App\Domains\Conteneurs\Support\Iso6346;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conteneur>
 *
 * Le numéro est généré valide ISO 6346 (chiffre de contrôle calculé).
 */
final class ConteneurFactory extends Factory
{
    protected $model = Conteneur::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = strtoupper(fake()->lexify('???')).'U'.fake()->numerify('######');
        $numero = $base.Iso6346::chiffreDeControle($base);

        return [
            'bl_id' => Bl::factory(),
            'numero' => $numero,
            'type' => fake()->randomElement(TypeConteneur::cases()),
            'statut' => fake()->randomElement(StatutConteneur::cases()),
            'source_numero' => fake()->randomElement(SourceNumeroConteneur::cases()),
        ];
    }
}
