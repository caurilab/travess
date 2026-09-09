<?php

declare(strict_types=1);

use App\Domains\Alertes\Enums\CanalNotification;
use App\Domains\Alertes\Enums\StatutNotification;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des envois (push / whatsapp / email / desktop) avec statut.
 *
 * destinataire_id est une FK simple vers users (hors schéma composite, ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('destinataire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('canal');
            $table->string('type_evenement'); // type d'événement métier
            $table->string('statut')->default(StatutNotification::EnAttente->value);
            $table->string('sujet')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamp('envoye_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
        });

        $canaux = "'".implode("','", CanalNotification::valeurs())."'";
        $statuts = "'".implode("','", StatutNotification::valeurs())."'";
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_canal_check CHECK (canal IN ({$canaux}))");
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('notifications');
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
