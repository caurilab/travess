<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connaissements (Bills of Lading) rattachés à un dossier.
 *
 * Table parente scopée (UNIQUE(id, tenant_id)) référencée par conteneurs.
 * navire_imo est la clé d'identification fiable ; navire_nom est indicatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bls', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->string('numero');
            $table->foreignUuid('armateur_id'); // FK composite ci-dessous
            $table->string('navire_nom')->nullable(); // indicatif
            $table->string('navire_imo')->nullable(); // clé d'identification navire
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            $table->foreign(['armateur_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('armateurs')->cascadeOnDelete();

            // Cible des FK composites des tables filles (conteneurs.bl_id).
            $table->unique(['id', 'tenant_id']);
        });

        RlsTenant::activer('bls');
    }

    public function down(): void
    {
        Schema::dropIfExists('bls');
    }
};
