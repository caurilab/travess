<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comptes client du portail (ADR-013, décision produit 7.2) : identifiés par
 * téléphone (E.164) + OTP. L'email devient facultatif (les transitaires gardent
 * email + mot de passe). Le téléphone est unique à l'échelle de la plateforme
 * (non-cumul : un identifiant = un compte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('telephone')->nullable()->after('email');
            $table->unique('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['telephone']);
            $table->dropColumn('telephone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
