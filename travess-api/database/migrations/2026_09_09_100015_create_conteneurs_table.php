<?php

declare(strict_types=1);

use App\Domains\Conteneurs\Enums\SourceNumeroConteneur;
use App\Domains\Conteneurs\Enums\StatutConteneur;
use App\Domains\Conteneurs\Enums\TypeConteneur;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conteneurs rattachés à un connaissement.
 *
 * Table parente scopée (UNIQUE(id, tenant_id)) référencée par franchises,
 * suivi_tracking et alertes. `numero` est validé ISO 6346 en amont (Iso6346),
 * pas revalidé en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conteneurs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('bl_id'); // FK composite ci-dessous
            $table->string('numero'); // validé ISO 6346 en amont
            $table->string('type');
            $table->string('statut')->default(StatutConteneur::ATraiter->value);
            $table->string('source_numero')->default(SourceNumeroConteneur::Manuel->value);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('bl_id');

            $table->foreign(['bl_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('bls')->cascadeOnDelete();

            // Cible des FK composites des tables filles.
            $table->unique(['id', 'tenant_id']);
        });

        $types = "'".implode("','", TypeConteneur::valeurs())."'";
        $statuts = "'".implode("','", StatutConteneur::valeurs())."'";
        $sources = "'".implode("','", SourceNumeroConteneur::valeurs())."'";
        DB::statement("ALTER TABLE conteneurs ADD CONSTRAINT conteneurs_type_check CHECK (type IN ({$types}))");
        DB::statement("ALTER TABLE conteneurs ADD CONSTRAINT conteneurs_statut_check CHECK (statut IN ({$statuts}))");
        DB::statement("ALTER TABLE conteneurs ADD CONSTRAINT conteneurs_source_numero_check CHECK (source_numero IN ({$sources}))");

        RlsTenant::activer('conteneurs');
    }

    public function down(): void
    {
        Schema::dropIfExists('conteneurs');
    }
};
