<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Identity\Enums\RoleUtilisateur;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nom' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => RoleUtilisateur::Agent->value,
            'preferences_notif' => [],
            'remember_token' => Str::random(10),
        ];
    }

    public function pourTenant(Tenant|string $tenant): static
    {
        return $this->state(fn (array $attributes): array => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
        ]);
    }

    public function role(RoleUtilisateur $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role->value,
        ]);
    }

    public function nonVerifie(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
