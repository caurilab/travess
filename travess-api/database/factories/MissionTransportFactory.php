<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Transport\Enums\StatutMission;
use App\Domains\Transport\Models\MissionTransport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissionTransport>
 */
final class MissionTransportFactory extends Factory
{
    protected $model = MissionTransport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'chauffeur_id' => null,
            'camion' => strtoupper(fake()->bothify('??-###-??')),
            'statut' => fake()->randomElement(StatutMission::cases()),
            'positions' => [],
            'lien_suivi_public' => fake()->optional()->sha1(),
            'bon_livraison_doc_id' => null,
        ];
    }
}
