<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Tenancy\Enums\PlanTenant;
use App\Domains\Tenancy\Enums\StatutTenant;
use App\Domains\Tenancy\Enums\TypeTenant;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'type' => TypeTenant::Transitaire->value,
            'plan' => PlanTenant::Essentiel->value,
            'statut' => StatutTenant::Actif->value,
            'quota_ia_mensuel' => 100,
            'quota_tracking_mensuel' => 1000,
            'parametres' => [],
        ];
    }

    /**
     * Workspace client (portail) : type client, sans quota IA/tracking.
     */
    public function client(): static
    {
        return $this->state(fn (): array => [
            'type' => TypeTenant::Client->value,
            'quota_ia_mensuel' => 0,
            'quota_tracking_mensuel' => 0,
        ]);
    }
}
