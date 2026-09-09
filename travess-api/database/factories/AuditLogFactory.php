<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
final class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'entite' => fake()->randomElement(['dossier', 'conteneur', 'paiement', 'document']),
            'entite_id' => (string) Str::uuid7(),
            'action' => fake()->randomElement(['creation', 'modification', 'validation', 'cloture']),
            'avant' => null,
            'apres' => ['statut' => fake()->word()],
            'at' => now(),
        ];
    }
}
