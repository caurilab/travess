<?php

declare(strict_types=1);

use App\Domains\Transport\Enums\StatutMission;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Missions de transport terrestre (livraison), avec trace géolocalisée.
 *
 * Référence dossiers et documents (bon_livraison_doc_id) par FK composite ;
 * chauffeur_id est une FK simple vers users (hors schéma composite, ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions_transport', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->foreignUuid('chauffeur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('camion')->nullable();
            $table->string('statut')->default(StatutMission::Prevue->value);
            $table->jsonb('positions')->default('[]'); // trace géolocalisée
            $table->string('lien_suivi_public')->nullable(); // token lecture seule client
            $table->foreignUuid('bon_livraison_doc_id')->nullable(); // FK composite ci-dessous
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            // Nullable : pas de ON DELETE (NO ACTION) — une composite ne peut pas
            // être mise à NULL (tenant_id NOT NULL). Nettoyage via cascade tenant.
            $table->foreign(['bon_livraison_doc_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('documents');
        });

        $statuts = "'".implode("','", StatutMission::valeurs())."'";
        DB::statement("ALTER TABLE missions_transport ADD CONSTRAINT missions_transport_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('missions_transport');
    }

    public function down(): void
    {
        Schema::dropIfExists('missions_transport');
    }
};
