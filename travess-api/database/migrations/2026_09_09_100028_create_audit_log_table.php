<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal d'audit inaltérable (append-only) : trace des actions par tenant.
 *
 * user_id est une FK simple vers users (hors schéma composite, ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entite');
            $table->uuid('entite_id');
            $table->string('action');
            $table->jsonb('avant')->nullable();
            $table->jsonb('apres')->nullable();
            $table->timestamp('at'); // horodatage inaltérable
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['entite', 'entite_id']);
        });

        RlsTenant::activer('audit_log');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
