<?php

declare(strict_types=1);

use App\Domains\Consommation\Enums\ServiceConsomme;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compteurs de consommation par service et par période (paliers, dépassement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consommation_service', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('service');
            $table->string('periode'); // mois, ex. « 2026-09 »
            $table->unsignedInteger('quantite')->default(0);
            $table->timestamps();

            $table->index('tenant_id');

            // Un seul compteur par (tenant, service, période).
            $table->unique(['tenant_id', 'service', 'periode']);
        });

        $services = "'".implode("','", ServiceConsomme::valeurs())."'";
        DB::statement("ALTER TABLE consommation_service ADD CONSTRAINT consommation_service_service_check CHECK (service IN ({$services}))");

        RlsTenant::activer('consommation_service');
    }

    public function down(): void
    {
        Schema::dropIfExists('consommation_service');
    }
};
