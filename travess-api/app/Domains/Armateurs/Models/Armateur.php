<?php

declare(strict_types=1);

namespace App\Domains\Armateurs\Models;

use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\ArmateurFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Armateur (compagnie maritime) et son barème personnalisé par tenant.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $nom
 * @property string|null $nom_api
 * @property string|null $email
 * @property array<string, mixed> $prefixes
 * @property bool $trackable
 * @property array<string, mixed> $bareme_surestaries
 * @property array<string, mixed> $bareme_detention
 * @property array<string, mixed>|null $imap_config
 */
final class Armateur extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'nom',
        'nom_api',
        'email',
        'prefixes',
        'trackable',
        'bareme_surestaries',
        'bareme_detention',
        'imap_config',
    ];

    protected function casts(): array
    {
        return [
            'prefixes' => 'array',
            'trackable' => 'boolean',
            'bareme_surestaries' => 'array',
            'bareme_detention' => 'array',
            // Secret (identifiants IMAP) chiffré au repos (cf. ADR-005).
            'imap_config' => 'encrypted:array',
        ];
    }

    protected static function newFactory(): Factory
    {
        return ArmateurFactory::new();
    }
}
