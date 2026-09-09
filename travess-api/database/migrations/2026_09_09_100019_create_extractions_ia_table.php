<?php

declare(strict_types=1);

use App\Domains\Documents\Enums\StatutExtraction;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extractions IA d'un document : l'IA propose, l'humain valide (principe n°5).
 *
 * Référence documents par FK composite ; valide_par est une FK simple vers
 * users (hors schéma composite, ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extractions_ia', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('document_id'); // FK composite ci-dessous
            $table->string('statut')->default(StatutExtraction::EnFile->value);
            $table->jsonb('champs')->default('{}');      // {champ: {valeur, confiance, zone_source}}
            $table->jsonb('corrections')->default('{}'); // corrections agent (amélioration continue)
            $table->foreignUuid('valide_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('valide_at')->nullable();
            $table->decimal('cout_unite', 15, 2)->default(0); // mesure de consommation
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('document_id');

            $table->foreign(['document_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('documents')->cascadeOnDelete();
        });

        $statuts = "'".implode("','", StatutExtraction::valeurs())."'";
        DB::statement("ALTER TABLE extractions_ia ADD CONSTRAINT extractions_ia_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('extractions_ia');
    }

    public function down(): void
    {
        Schema::dropIfExists('extractions_ia');
    }
};
