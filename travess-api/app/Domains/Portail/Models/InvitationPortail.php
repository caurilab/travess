<?php

declare(strict_types=1);

namespace App\Domains\Portail\Models;

use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\StatutInvitation;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\InvitationPortailFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitation d'onboarding portail (ADR-013). Scopée par l'émetteur (transitaire).
 *
 * Le token n'est JAMAIS stocké en clair (seulement `token_hash`) ; le
 * destinataire est chiffré au repos (cast encrypted). La lecture publique (chemin
 * de réclamation) se fait TenantScope levé, bornée par la RLS au token présenté.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $token_hash
 * @property string $dossier_id
 * @property string $client_id
 * @property CanalMessage $canal
 * @property string $destinataire_chiffre
 * @property NiveauAcces $niveau
 * @property StatutInvitation $statut
 * @property Carbon $expire_at
 * @property bool $usage_unique
 * @property int $tentatives
 * @property string|null $consumed_by_user_id
 * @property Carbon|null $consumed_at
 * @property Carbon|null $revoked_at
 * @property string|null $created_by
 */
final class InvitationPortail extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'invitation_portail';

    protected $fillable = [
        'token_hash',
        'dossier_id',
        'client_id',
        'canal',
        'destinataire_chiffre',
        'niveau',
        'statut',
        'expire_at',
        'usage_unique',
        'tentatives',
        'consumed_by_user_id',
        'consumed_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = [
        'token_hash',
        'destinataire_chiffre',
    ];

    protected function casts(): array
    {
        return [
            'canal' => CanalMessage::class,
            'niveau' => NiveauAcces::class,
            'statut' => StatutInvitation::class,
            'destinataire_chiffre' => 'encrypted',
            'expire_at' => 'datetime',
            'consumed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'usage_unique' => 'boolean',
            'tentatives' => 'integer',
        ];
    }

    public function estReclamable(): bool
    {
        return $this->statut === StatutInvitation::Emise
            && $this->expire_at->isFuture();
    }

    /**
     * @return BelongsTo<Dossier, $this>
     */
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    protected static function newFactory(): Factory
    {
        return InvitationPortailFactory::new();
    }
}
