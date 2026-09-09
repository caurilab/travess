<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Tenancy\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 *
 * Le tenant_id n'est pas défini ici : le trait BelongsToTenant le renseigne
 * depuis le contexte tenant courant à la création. Un contexte tenant doit
 * donc être établi (TenantContext::set()) avant de créer un Client via factory.
 */
final class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->company(),
            'contact' => fake()->name(),
            'canaux' => [
                'email' => fake()->companyEmail(),
                'whatsapp' => fake()->e164PhoneNumber(),
            ],
        ];
    }
}
