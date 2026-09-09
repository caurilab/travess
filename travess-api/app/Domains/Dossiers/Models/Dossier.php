<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Models;

use App\Domains\Conteneurs\Models\Bl;
use App\Domains\Documents\Models\Document;
use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Dossiers\Enums\StatutDossier;
use App\Domains\Identity\Models\User;
use App\Domains\Tenancy\Models\Client;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\DossierFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dossier de transit : unité de travail centrale d'un tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $reference
 * @property SensDossier $sens
 * @property string $client_id
 * @property StatutDossier $statut
 * @property PostureDossier $posture
 * @property string|null $motif_blocage
 */
final class Dossier extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'reference',
        'sens',
        'client_id',
        'statut',
        'posture',
        'motif_blocage',
    ];

    protected function casts(): array
    {
        return [
            'sens' => SensDossier::class,
            'statut' => StatutDossier::class,
            'posture' => PostureDossier::class,
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Etape, $this>
     */
    public function etapes(): HasMany
    {
        return $this->hasMany(Etape::class)->orderBy('ordre');
    }

    /**
     * Agents assignés (assignation multiple, PRD §3.1).
     *
     * @return BelongsToMany<User, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dossier_user');
    }

    /**
     * @return HasMany<Bl, $this>
     */
    public function bls(): HasMany
    {
        return $this->hasMany(Bl::class);
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    protected static function newFactory(): Factory
    {
        return DossierFactory::new();
    }
}
