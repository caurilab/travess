<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Portail\Enums\StatutDemande;
use App\Domains\Portail\Models\DemandeAssignation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemandeAssignation>
 *
 * Identifiants (dossier, tenants) fournis explicitement par le test.
 */
final class DemandeAssignationFactory extends Factory
{
    protected $model = DemandeAssignation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'statut' => StatutDemande::EnAttente->value,
            'message' => null,
            'expire_at' => now()->addDays(7),
        ];
    }
}
