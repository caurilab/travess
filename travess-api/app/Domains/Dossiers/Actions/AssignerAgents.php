<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Actions;

use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Models\Dossier;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * (Ré)assigne les agents d'un dossier (pivot dossier_user). Le pivot portant
 * tenant_id (scopé + RLS), on le renseigne explicitement depuis le contexte.
 */
final class AssignerAgents
{
    public function __construct(
        private readonly Auditeur $auditeur,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  list<string>  $userIds
     */
    public function executer(Dossier $dossier, array $userIds): Dossier
    {
        return DB::transaction(function () use ($dossier, $userIds): Dossier {
            $tenantId = $this->tenant->idOrFail();

            $avant = $dossier->agents()->pluck('users.id')->all();

            $pivot = [];
            foreach ($userIds as $id) {
                $pivot[$id] = ['tenant_id' => $tenantId];
            }

            $dossier->agents()->sync($pivot);

            $this->auditeur->enregistrer(
                'dossier',
                $dossier->id,
                'dossier.assignation',
                ['agents' => $avant],
                ['agents' => $userIds],
            );

            return $dossier->load('agents');
        });
    }
}
