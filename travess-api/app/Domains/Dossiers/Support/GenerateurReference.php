<?php

declare(strict_types=1);

namespace App\Domains\Dossiers\Support;

use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Shared\Context\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Génère la référence d'un dossier, unique par tenant, remise à zéro chaque
 * année : IMP-2026-0001 (import) / EXP-2026-0001 (export).
 *
 * À appeler DANS la transaction de création : un verrou consultatif
 * (pg_advisory_xact_lock) sérialise la génération par (tenant, année, sens)
 * pour éviter les collisions concurrentes, et la contrainte
 * UNIQUE(tenant_id, reference) reste le filet ultime.
 */
final class GenerateurReference
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function suivante(SensDossier $sens): string
    {
        $tenantId = $this->tenant->idOrFail();
        $prefixe = $sens === SensDossier::Import ? 'IMP' : 'EXP';
        $annee = (int) now()->format('Y');

        // Sérialise la génération pour cette combinaison (verrou relâché au commit).
        DB::selectOne('select pg_advisory_xact_lock(hashtext(?))', ["ref:{$tenantId}:{$annee}:{$prefixe}"]);

        $motif = "{$prefixe}-{$annee}-";

        $derniere = Dossier::where('reference', 'like', $motif.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $numero = $derniere !== null
            ? ((int) substr((string) $derniere, strlen($motif))) + 1
            : 1;

        return $motif.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
    }
}
