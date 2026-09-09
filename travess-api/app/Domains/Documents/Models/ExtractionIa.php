<?php

declare(strict_types=1);

namespace App\Domains\Documents\Models;

use App\Domains\Documents\Enums\StatutExtraction;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ExtractionIaFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Extraction IA d'un document : l'IA propose, l'humain valide (principe n°5).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property StatutExtraction $statut
 * @property array<string, mixed> $champs
 * @property array<string, mixed> $corrections
 * @property string|null $valide_par
 * @property Carbon|null $valide_at
 * @property string $cout_unite nombre d'unités consommées par cette extraction (décompté au quota, cf. DecompteConsommationIa)
 */
final class ExtractionIa extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'extractions_ia';

    protected $fillable = [
        'document_id',
        'statut',
        'champs',
        'corrections',
        'valide_par',
        'valide_at',
        'cout_unite',
    ];

    protected function casts(): array
    {
        return [
            'statut' => StatutExtraction::class,
            'champs' => 'array',
            'corrections' => 'array',
            'valide_at' => 'datetime',
            'cout_unite' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    protected static function newFactory(): Factory
    {
        return ExtractionIaFactory::new();
    }
}
