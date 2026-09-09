<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référence de dossier auto-générée et unique par tenant (IMP-2026-0001…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'reference']);
        });
    }
};
