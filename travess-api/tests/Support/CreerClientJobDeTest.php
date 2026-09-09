<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domains\Tenancy\Models\Client;
use App\Shared\Jobs\JobTenantScoped;

/**
 * Job-fixture pour valider JobTenantScoped : crée un client dans le contexte du
 * tenant fourni. Sert uniquement aux tests.
 */
final class CreerClientJobDeTest extends JobTenantScoped
{
    public function __construct(string $tenantId, private readonly string $nom)
    {
        parent::__construct($tenantId);
    }

    protected function traiter(): void
    {
        Client::factory()->create(['nom' => $this->nom]);
    }
}
