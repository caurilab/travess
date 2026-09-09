<?php

declare(strict_types=1);

use App\Domains\Documents\Enums\OrigineDocument;
use App\Domains\Documents\Enums\StatutIngestion;
use App\Domains\Documents\Enums\TypeDocument;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documents rattachés à un dossier (stockage objet + pipeline d'ingestion IA).
 *
 * Table parente scopée (UNIQUE(id, tenant_id)) référencée par extractions_ia et
 * missions_transport (bon_livraison_doc_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->string('type');
            $table->string('chemin_stockage'); // objet S3
            $table->string('origine');
            $table->string('statut_ingestion')->default(StatutIngestion::None->value);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();

            // Cible des FK composites des tables filles.
            $table->unique(['id', 'tenant_id']);
        });

        $types = "'".implode("','", TypeDocument::valeurs())."'";
        $origines = "'".implode("','", OrigineDocument::valeurs())."'";
        $statuts = "'".implode("','", StatutIngestion::valeurs())."'";
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_type_check CHECK (type IN ({$types}))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_origine_check CHECK (origine IN ({$origines}))");
        DB::statement("ALTER TABLE documents ADD CONSTRAINT documents_statut_ingestion_check CHECK (statut_ingestion IN ({$statuts}))");

        RlsTenant::activer('documents');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
