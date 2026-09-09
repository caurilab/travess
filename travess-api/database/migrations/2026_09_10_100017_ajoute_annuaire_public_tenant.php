<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilité d'un transitaire dans l'annuaire de la plateforme (ADR-013, 7.4).
 * OPT-IN (décision produit) : un transitaire n'apparaît que s'il l'a activé ;
 * un client autonome ne peut assigner qu'un transitaire opté-dans.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('annuaire_public')->default(false)->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('annuaire_public');
        });
    }
};
