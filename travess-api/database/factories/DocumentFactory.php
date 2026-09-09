<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Documents\Enums\OrigineDocument;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Enums\TypeDocument;
use App\Domains\Documents\Models\Document;
use App\Domains\Dossiers\Models\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
final class DocumentFactory extends Factory
{
    protected $model = Document::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'type' => fake()->randomElement(TypeDocument::cases()),
            'chemin_stockage' => 'documents/'.fake()->uuid().'.pdf',
            'origine' => fake()->randomElement(OrigineDocument::cases()),
            'statut_ingestion' => fake()->randomElement(StatutIngestion::cases()),
        ];
    }
}
