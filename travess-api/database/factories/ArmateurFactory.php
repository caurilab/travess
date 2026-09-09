<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Armateurs\Models\Armateur;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Armateur>
 *
 * tenant_id non défini ici : posé par BelongsToTenant depuis le contexte.
 */
final class ArmateurFactory extends Factory
{
    protected $model = Armateur::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nom = fake()->randomElement(['Maersk', 'MSC', 'CMA CGM', 'Hapag-Lloyd', 'Grimaldi']);

        return [
            'nom' => $nom,
            'nom_api' => strtoupper(str_replace([' ', '-'], '_', $nom)),
            'prefixes' => [strtoupper(fake()->lexify('???')).'U'],
            'trackable' => $nom !== 'Grimaldi',
            'bareme_surestaries' => ['paliers' => [['jours' => 7, 'tarif' => 50], ['jours' => 14, 'tarif' => 100]]],
            'bareme_detention' => ['paliers' => [['jours' => 5, 'tarif' => 40]]],
            'imap_config' => null,
        ];
    }
}
