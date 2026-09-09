<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Montant d'alerte en entier (XOF) et idempotence du moteur d'alertes :
 * une seule alerte par (tenant, conteneur, type) — un re-run du scheduler ne
 * recrée pas la même alerte.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE alertes ALTER COLUMN montant_menacant TYPE numeric(15,0)');

        Schema::table('alertes', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'conteneur_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('alertes', function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'conteneur_id', 'type']);
        });

        DB::statement('ALTER TABLE alertes ALTER COLUMN montant_menacant TYPE numeric(15,2)');
    }
};
