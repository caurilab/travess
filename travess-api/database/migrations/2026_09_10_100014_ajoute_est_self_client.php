<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche « soi-même » d'un client autonome (ADR-013, Lot 7.3) : dans son
 * workspace, le compte client est son propre donneur d'ordre. Marqueur pour
 * retrouver cette fiche de façon déterministe (une seule par workspace client).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->boolean('est_self')->default(false)->after('nom');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn('est_self');
        });
    }
};
