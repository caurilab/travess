<?php

declare(strict_types=1);

use App\Domains\Dossiers\Enums\SensDossier;
use App\Domains\Dossiers\Enums\StatutDossier;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dossiers de transit : unité de travail centrale.
 *
 * Table parente scopée (UNIQUE(id, tenant_id)) référencée par etapes, bls,
 * documents, charges, honoraires, encaissements, alertes, missions, paiements…
 * Référence clients via FK composite (défense en profondeur, ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('reference'); // numéro interne
            $table->string('sens');
            $table->foreignUuid('client_id'); // FK composite ci-dessous
            $table->string('statut')->default(StatutDossier::Ouvert->value);
            $table->string('motif_blocage')->nullable(); // ex. « attente paiement client »
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('client_id');

            // FK composite vers le parent scopé : un dossier ne peut pointer un
            // client d'un autre tenant.
            $table->foreign(['client_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('clients')->cascadeOnDelete();

            // Cible des FK composites des tables filles.
            $table->unique(['id', 'tenant_id']);
        });

        $sens = "'".implode("','", SensDossier::valeurs())."'";
        $statuts = "'".implode("','", StatutDossier::valeurs())."'";
        DB::statement("ALTER TABLE dossiers ADD CONSTRAINT dossiers_sens_check CHECK (sens IN ({$sens}))");
        DB::statement("ALTER TABLE dossiers ADD CONSTRAINT dossiers_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('dossiers');
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers');
    }
};
