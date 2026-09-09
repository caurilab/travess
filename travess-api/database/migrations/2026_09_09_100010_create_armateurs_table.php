<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Armateurs (compagnies maritimes) et leurs barèmes personnalisés par tenant.
 *
 * Table parente scopée : porte UNIQUE(id, tenant_id) car référencée par bls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armateurs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nom'); // Maersk, MSC, CMA CGM, Grimaldi…
            $table->string('nom_api')->nullable(); // nom normalisé JSONCargo ; null si non couvert
            $table->jsonb('prefixes')->default('[]'); // préfixes de conteneur connus
            $table->boolean('trackable')->default(true); // false pour Grimaldi & non couverts
            $table->jsonb('bareme_surestaries')->default('{}'); // paliers jours → tarif/jour
            $table->jsonb('bareme_detention')->default('{}');
            $table->text('imap_config')->nullable(); // fallback IMAP, chiffré au repos (cast encrypted:array)
            $table->timestamps();

            $table->index('tenant_id');

            // Cible des FK composites des tables filles (bls.armateur_id).
            $table->unique(['id', 'tenant_id']);
        });

        RlsTenant::activer('armateurs');
    }

    public function down(): void
    {
        Schema::dropIfExists('armateurs');
    }
};
