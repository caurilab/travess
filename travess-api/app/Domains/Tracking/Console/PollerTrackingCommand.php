<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Console;

use App\Domains\Tenancy\Models\Tenant;
use App\Domains\Tracking\Jobs\PollerTrackingTenant;
use Illuminate\Console\Command;

/**
 * Orchestrateur du tracking : énumère les tenants (racine non scopée) et
 * dispatche un job de poll par tenant (chacun rétablit son contexte et ne
 * réveille que ses conteneurs échus). Jamais de boucle inter-tenant mutante.
 */
final class PollerTrackingCommand extends Command
{
    protected $signature = 'tracking:poller';

    protected $description = 'Poll les conteneurs échus (tracking), par tenant, en respectant le plafond de quota.';

    public function handle(): int
    {
        $nombre = 0;

        Tenant::query()->each(function (Tenant $tenant) use (&$nombre): void {
            PollerTrackingTenant::dispatch($tenant->id);
            $nombre++;
        });

        $this->info("Poll tracking dispatché pour {$nombre} tenant(s).");

        return self::SUCCESS;
    }
}
