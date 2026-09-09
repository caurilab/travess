<?php

declare(strict_types=1);

use App\Domains\Tenancy\Enums\PlanTenant;
use App\Domains\Tenancy\Enums\StatutTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table racine du multi-tenant : la société de transit.
 *
 * Seule table métier SANS colonne tenant_id (elle EST le tenant). Non scopée
 * par TenantScope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('plan')->default(PlanTenant::Essentiel->value);
            $table->string('statut')->default(StatutTenant::Actif->value);
            $table->unsignedInteger('quota_ia_mensuel')->default(0);
            $table->unsignedInteger('quota_tracking_mensuel')->default(0);
            $table->jsonb('parametres')->default('{}');
            $table->timestamps();
        });

        // Enums en varchar + contrainte CHECK (évolution plus simple qu'un type ENUM PG).
        $plans = "'".implode("','", PlanTenant::valeurs())."'";
        $statuts = "'".implode("','", StatutTenant::valeurs())."'";
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_plan_check CHECK (plan IN ({$plans}))");
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_statut_check CHECK (statut IN ({$statuts}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
