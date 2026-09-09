<?php

declare(strict_types=1);

use App\Domains\Alertes\Enums\StatutAlerte;
use App\Domains\Alertes\Enums\TypeAlerte;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alertes métier (paliers surestaries/détention, SLA dépassé, blocage).
 *
 * Référence dossiers (obligatoire) et conteneurs (optionnel) par FK composite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->foreignUuid('conteneur_id')->nullable(); // FK composite ci-dessous
            $table->string('type');
            $table->decimal('montant_menacant', 15, 2)->nullable();
            $table->string('statut')->default(StatutAlerte::Ouverte->value);
            $table->jsonb('canaux_envoyes')->default('[]');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            // conteneur_id nullable : pas de ON DELETE (NO ACTION), nettoyage via
            // cascade tenant.
            $table->foreign(['conteneur_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('conteneurs');
        });

        $types = "'".implode("','", TypeAlerte::valeurs())."'";
        $statuts = "'".implode("','", StatutAlerte::valeurs())."'";
        DB::statement("ALTER TABLE alertes ADD CONSTRAINT alertes_type_check CHECK (type IN ({$types}))");
        DB::statement("ALTER TABLE alertes ADD CONSTRAINT alertes_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('alertes');
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};
