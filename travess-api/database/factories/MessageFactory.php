<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Correspondance\Enums\DirectionMessage;
use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Correspondance\Models\Message;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Messagerie\Enums\CanalMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 *
 * tenant_id non défini ici : posé par BelongsToTenant depuis le contexte. Le
 * dossier est créé dans le même contexte tenant (FK composite cohérente).
 */
final class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_id' => Dossier::factory(),
            'armateur_id' => null,
            'auteur_id' => null,
            'direction' => DirectionMessage::Sortant->value,
            'type_demande' => TypeDemande::RelanceSurestaries->value,
            'canal' => CanalMessage::Email->value,
            'destinataire_adresse' => fake()->safeEmail(),
            'objet' => 'Relance surestaries — dossier '.strtoupper(fake()->bothify('??######')),
            'corps' => "Bonjour,\n\n[message]\n\nCordialement,\nVotre transitaire",
            'statut' => StatutMessage::EnFile->value,
            'reference_externe' => null,
            'erreur' => null,
            'envoye_at' => null,
            'recu_at' => null,
            'meta' => [],
        ];
    }

    public function direction(DirectionMessage $direction): static
    {
        return $this->state(fn (array $attributs): array => [
            'direction' => $direction->value,
        ]);
    }

    public function canal(CanalMessage $canal): static
    {
        return $this->state(fn (array $attributs): array => [
            'canal' => $canal->value,
        ]);
    }

    public function type(?TypeDemande $type): static
    {
        return $this->state(fn (array $attributs): array => [
            'type_demande' => $type?->value,
        ]);
    }

    public function statut(StatutMessage $statut): static
    {
        return $this->state(fn (array $attributs): array => [
            'statut' => $statut->value,
        ]);
    }
}
