<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table pivot : assignation d'agents (users) à un dossier (assignation
 * multiple). Sans modèle Eloquent dédié.
 *
 * Scopée par tenant (RLS + tenant_id). Référence dossiers par FK composite et
 * users par FK simple (users hors schéma composite, cf. ADR-004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossier_user', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('tenant_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();

            $table->unique(['dossier_id', 'user_id']);
        });

        RlsTenant::activer('dossier_user');
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_user');
    }
};
