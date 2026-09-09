<?php

declare(strict_types=1);

namespace App\Domains\Portail\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Active/désactive la visibilité du transitaire courant dans l'annuaire de la
 * plateforme (ADR-013, 7.4). Audité. Le tenant n'étant pas scopé, on le charge
 * explicitement (le contexte fournit son id).
 */
final class DefinirVisibiliteAnnuaire
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly Auditeur $auditeur,
    ) {}

    public function executer(bool $public): bool
    {
        return DB::transaction(function () use ($public): bool {
            $tenant = $this->tenant->runBypassed(fn (): Tenant => Tenant::findOrFail($this->tenant->idOrFail()));

            $tenant->forceFill(['annuaire_public' => $public])->save();

            $this->auditeur->enregistrer('tenant', $tenant->id, 'annuaire.visibilite', null, [
                'annuaire_public' => $tenant->annuaire_public,
            ]);

            return $tenant->annuaire_public;
        });
    }
}
