<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Domains\Alertes\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destinataire_id' => null,
            'canal' => fake()->randomElement(CanalNotification::cases()),
            'type_evenement' => fake()->randomElement(['surestaries', 'sla', 'blocage', 'paiement']),
            'statut' => fake()->randomElement(StatutNotification::cases()),
            'sujet' => fake()->sentence(4),
            'meta' => [],
            'envoye_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
