<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\StatutInvitation;
use App\Domains\Portail\Models\InvitationPortail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvitationPortail>
 *
 * dossier_id / client_id fournis par le test (FK composites du tenant courant).
 */
final class InvitationPortailFactory extends Factory
{
    protected $model = InvitationPortail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token_hash' => hash('sha256', Str::random(40)),
            'canal' => CanalMessage::Whatsapp->value,
            'destinataire_chiffre' => '+22890'.fake()->numerify('######'),
            'niveau' => NiveauAcces::Limite->value,
            'statut' => StatutInvitation::Emise->value,
            'expire_at' => now()->addHours(72),
            'usage_unique' => true,
            'tentatives' => 0,
        ];
    }
}
