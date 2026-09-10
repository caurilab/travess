<?php

declare(strict_types=1);

namespace App\Domains\Correspondance\Models;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Correspondance\Enums\DirectionMessage;
use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Identity\Models\User;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Message de correspondance armateur, rattaché à un dossier.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string|null $armateur_id
 * @property string|null $auteur_id
 * @property DirectionMessage $direction
 * @property TypeDemande|null $type_demande
 * @property CanalMessage $canal
 * @property string $destinataire_adresse
 * @property string $objet
 * @property string $corps
 * @property StatutMessage $statut
 * @property string|null $reference_externe
 * @property string|null $erreur
 * @property \Illuminate\Support\Carbon|null $envoye_at
 * @property \Illuminate\Support\Carbon|null $recu_at
 * @property array<string, mixed> $meta
 */
final class Message extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'armateur_id',
        'auteur_id',
        'direction',
        'type_demande',
        'canal',
        'destinataire_adresse',
        'objet',
        'corps',
        'statut',
        'reference_externe',
        'erreur',
        'envoye_at',
        'recu_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'direction' => DirectionMessage::class,
            'type_demande' => TypeDemande::class,
            'canal' => CanalMessage::class,
            'statut' => StatutMessage::class,
            'envoye_at' => 'datetime',
            'recu_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    /**
     * @return BelongsTo<Armateur, $this>
     */
    public function armateur(): BelongsTo
    {
        return $this->belongsTo(Armateur::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    protected static function newFactory(): Factory
    {
        return MessageFactory::new();
    }
}
