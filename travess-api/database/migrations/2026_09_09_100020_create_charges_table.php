<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charges (argent sorti) rattachées à un dossier.
 *
 * Table parente scopée (UNIQUE(id, tenant_id)) référencée par encaissements
 * (rapproche_charge_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->string('libelle');
            $table->decimal('montant', 15, 2);
            $table->boolean('avancee_pour_client')->default(false);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();

            // Cible des FK composites des tables filles.
            $table->unique(['id', 'tenant_id']);
        });

        RlsTenant::activer('charges');
    }

    public function down(): void
    {
        Schema::dropIfExists('charges');
    }
};
