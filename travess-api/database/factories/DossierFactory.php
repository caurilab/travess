<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Dossiers\Enums\StatutDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Tenancy\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dossier>
 *
 * tenant_id non défini ici : posé par BelongsToTenant depuis le contexte. Le
 * client est créé dans le même contexte tenant (FK composite cohérente).
 */
final class DossierFactory extends Factory
{
    protected $model = Dossier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'DOS-'.fake()->unique()->numerify('######'),
            'sens' => fake()->randomElement(SensDossier::cases()),
            'client_id' => Client::factory(),
            'statut' => fake()->randomElement(StatutDossier::cases()),
            'motif_blocage' => null,
        ];
    }
}
