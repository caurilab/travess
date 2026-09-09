<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Client (donneur d'ordre) d'un tenant. Scopé par tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $nom
 * @property bool $est_self
 * @property string|null $contact
 * @property array<string, mixed> $canaux
 */
final class Client extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'nom',
        'est_self',
        'contact',
        'canaux',
    ];

    protected function casts(): array
    {
        return [
            'est_self' => 'boolean',
            'canaux' => 'array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ClientFactory::new();
    }
}
