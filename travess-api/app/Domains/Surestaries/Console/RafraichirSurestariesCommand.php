<?php

declare(strict_types=1);

namespace App\Domains\Surestaries\Console;

use App\Domains\Surestaries\Jobs\RafraichirSurestaries;
use App\Domains\Tenancy\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Orchestrateur : énumère les tenants (racine non scopée) et dispatche un job
 * de rafraîchissement par tenant (chacun rétablit son contexte). Jamais de
 * boucle inter-tenant unique mutant des lignes scopées.
 */
final class RafraichirSurestariesCommand extends Command
{
    protected $signature = 'surestaries:rafraichir';

    protected $description = 'Recalcule les franchises et génère les alertes de surestaries, par tenant.';

    public function handle(): int
    {
        $nombre = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$nombre): void {
            RafraichirSurestaries::dispatch($tenant->id);
            $nombre++;
        });

        $this->info("Rafraîchissement dispatché pour {$nombre} tenant(s).");

        return self::SUCCESS;
    }
}
