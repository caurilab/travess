<?php

declare(strict_types=1);

namespace App\Domains\Documents\Models;

use App\Domains\Documents\Enums\OrigineDocument;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Enums\TypeDocument;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Document rattaché à un dossier (objet de stockage + pipeline d'ingestion).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property TypeDocument $type
 * @property string $chemin_stockage
 * @property OrigineDocument $origine
 * @property StatutIngestion $statut_ingestion
 * @property string|null $nom_original
 * @property string|null $mime
 * @property int|null $taille
 */
final class Document extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'type',
        'chemin_stockage',
        'origine',
        'statut_ingestion',
        'nom_original',
        'mime',
        'taille',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeDocument::class,
            'origine' => OrigineDocument::class,
            'statut_ingestion' => StatutIngestion::class,
            'taille' => 'integer',
        ];
    }

    protected static function newFactory(): Factory
    {
        return DocumentFactory::new();
    }
}
