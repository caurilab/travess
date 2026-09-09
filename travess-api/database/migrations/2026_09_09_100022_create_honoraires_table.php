<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Honoraires (facturation du transitaire) avec numérotation légale continue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('honoraires', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->decimal('montant', 15, 2);
            $table->string('numero_facture'); // numérotation légale continue
            $table->string('pdf_chemin')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
        });

        RlsTenant::activer('honoraires');
    }

    public function down(): void
    {
        Schema::dropIfExists('honoraires');
    }
};
