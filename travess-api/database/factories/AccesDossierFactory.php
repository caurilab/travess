<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Models\AccesDossier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccesDossier>
 *
 * Table inter-tenant : les identifiants (dossier, tenants, bénéficiaire) sont
 * fournis explicitement par le test — pas de valeur par défaut plausible.
 */
final class AccesDossierFactory extends Factory
{
    protected $model = AccesDossier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'niveau' => NiveauAcces::Limite->value,
            'statut' => StatutAcces::Actif->value,
            'origine' => OrigineAcces::InvitationTransitaire->value,
            'created_by' => null,
            'revoked_at' => null,
        ];
    }
}
