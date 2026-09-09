<?php

declare(strict_types=1);

use App\Domains\Dossiers\Enums\StatutEtape;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Étapes de workflow d'un dossier (SLA éditable).
 *
 * Référence dossiers par FK composite ; responsable_id est une FK simple vers
 * users (hors schéma composite, ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etapes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->unsignedInteger('ordre');
            $table->string('libelle');
            $table->unsignedInteger('sla_jours')->default(0);
            $table->date('date_prevue')->nullable();
            $table->date('date_reelle')->nullable();
            $table->string('statut')->default(StatutEtape::AFaire->value);
            $table->foreignUuid('responsable_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
        });

        $statuts = "'".implode("','", StatutEtape::valeurs())."'";
        DB::statement("ALTER TABLE etapes ADD CONSTRAINT etapes_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('etapes');
    }

    public function down(): void
    {
        Schema::dropIfExists('etapes');
    }
};
