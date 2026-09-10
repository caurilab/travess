<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adresse e-mail de contact de l'armateur (carnet du tenant).
 *
 * Sert de destinataire par défaut de la correspondance ; l'adresse réellement
 * employée est figée (snapshot) sur chaque message pour la valeur probante.
 * Nullable : tous les armateurs n'ont pas de contact renseigné. PII — soumise
 * à la RLS de la table (armateurs est déjà scopée par tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('armateurs', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('nom_api');
        });
    }

    public function down(): void
    {
        Schema::table('armateurs', function (Blueprint $table): void {
            $table->dropColumn('email');
        });
    }
};
