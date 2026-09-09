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
            'bareme_surestaries' => self::baremeCanonique(),
            'bareme_detention' => self::baremeCanonique(),
            'imap_config' => null,
        ];
    }

    /**
     * Barème au format canonique attendu par le calcul (paliers progressifs par
     * type de conteneur, devise XOF, montants entiers).
     *
     * @return array<string, mixed>
     */
    public static function baremeCanonique(): array
    {
        return [
            'devise' => 'XOF',
            'paliers_par_type' => [
                '20' => [
                    ['de_jour' => 1, 'a_jour' => 5, 'tarif_jour' => 10000],
                    ['de_jour' => 6, 'a_jour' => 10, 'tarif_jour' => 20000],
                    ['de_jour' => 11, 'a_jour' => null, 'tarif_jour' => 35000],
                ],
                '40' => [
                    ['de_jour' => 1, 'a_jour' => 5, 'tarif_jour' => 15000],
                    ['de_jour' => 6, 'a_jour' => 10, 'tarif_jour' => 30000],
                    ['de_jour' => 11, 'a_jour' => null, 'tarif_jour' => 50000],
                ],
                'defaut' => [
                    ['de_jour' => 1, 'a_jour' => 10, 'tarif_jour' => 12000],
                    ['de_jour' => 11, 'a_jour' => null, 'tarif_jour' => 25000],
                ],
            ],
        ];
    }
}
