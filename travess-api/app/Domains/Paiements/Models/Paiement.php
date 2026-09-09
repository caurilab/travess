<?php

declare(strict_types=1);

namespace App\Domains\Paiements\Models;

use App\Domains\Paiements\Enums\CiblePaiement;
use App\Domains\Paiements\Enums\OperateurPaiement;
use App\Domains\Paiements\Enums\StatutPaiement;
use App\Shared\Concerns\BelongsToTenant;
use App\Shared\Models\BaseModel;
use Database\Factories\PaiementFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Paiement Mobile Money initié depuis le portail client.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $dossier_id
 * @property string $client_id
 * @property string $montant
 * @property CiblePaiement $cible
 * @property OperateurPaiement $operateur
 * @property StatutPaiement $statut
 * @property string|null $ref_agregateur
 * @property string $commission_travess
 * @property string|null $recu_chemin
 */
final class Paiement extends BaseModel
{
    use BelongsToTenant;

    protected $fillable = [
        'dossier_id',
        'client_id',
        'montant',
        'cible',
        'operateur',
        'statut',
        'ref_agregateur',
        'commission_travess',
        'recu_chemin',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'decimal:2',
            'cible' => CiblePaiement::class,
            'operateur' => OperateurPaiement::class,
            'statut' => StatutPaiement::class,
            'commission_travess' => 'decimal:2',
        ];
    }

    protected static function newFactory(): Factory
    {
        return PaiementFactory::new();
    }
}
