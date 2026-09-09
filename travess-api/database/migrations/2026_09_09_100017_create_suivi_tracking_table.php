<?php

declare(strict_types=1);

use App\Domains\Conteneurs\Enums\SourceSuiviTracking;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots de suivi conteneur (JSONCargo / IMAP / manuel).
 *
 * snapshot conserve la réponse brute normalisée (historique et analyse V2).
 * prochain_poll_prevu est calculé par le scheduler intelligent (shared-core).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suivi_tracking', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('conteneur_id'); // FK composite ci-dessous
            $table->string('source');
            $table->jsonb('snapshot')->default('{}'); // réponse brute normalisée
            $table->string('statut_conteneur')->nullable(); // mappé vers conteneur.statut
            $table->string('emplacement')->nullable();
            $table->timestamp('eta_destination')->nullable();
            $table->string('navire_nom')->nullable();
            $table->string('navire_imo')->nullable();
            $table->timestamp('prochain_poll_prevu')->nullable(); // calculé
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('conteneur_id');

            $table->foreign(['conteneur_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('conteneurs')->cascadeOnDelete();
        });

        $sources = "'".implode("','", SourceSuiviTracking::valeurs())."'";
        DB::statement("ALTER TABLE suivi_tracking ADD CONSTRAINT suivi_tracking_source_check CHECK (source IN ({$sources}))");

        RlsTenant::activer('suivi_tracking');
    }

    public function down(): void
    {
        Schema::dropIfExists('suivi_tracking');
    }
};
