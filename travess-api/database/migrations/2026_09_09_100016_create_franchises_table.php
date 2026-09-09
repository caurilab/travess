<?php

declare(strict_types=1);

use App\Domains\Conteneurs\Enums\TypeFranchise;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Franchises (surestaries / détention) d'un conteneur.
 *
 * date_fin_franchise, montant_en_cours, montant_menacant et actif sont CALCULÉS
 * par shared-core (jamais saisis à la main, docs/07 §12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franchises', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('conteneur_id'); // FK composite ci-dessous
            $table->string('type');
            $table->date('date_debut'); // ex. discharging / sortie port
            $table->unsignedInteger('jours_francs')->default(0);
            $table->date('date_fin_franchise')->nullable();      // calculée
            $table->decimal('montant_en_cours', 15, 2)->default(0); // calculée
            $table->decimal('montant_menacant', 15, 2)->default(0); // calculée
            $table->boolean('actif')->default(false);            // calculée
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('conteneur_id');

            $table->foreign(['conteneur_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('conteneurs')->cascadeOnDelete();
        });

        $types = "'".implode("','", TypeFranchise::valeurs())."'";
        DB::statement("ALTER TABLE franchises ADD CONSTRAINT franchises_type_check CHECK (type IN ({$types}))");

        RlsTenant::activer('franchises');
    }

    public function down(): void
    {
        Schema::dropIfExists('franchises');
    }
};
