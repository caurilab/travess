<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Métadonnées de fichier sur les documents (nom d'origine, type MIME, taille).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('nom_original')->nullable()->after('type');
            $table->string('mime')->nullable()->after('nom_original');
            $table->unsignedBigInteger('taille')->nullable()->after('mime'); // octets
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropColumn(['nom_original', 'mime', 'taille']);
        });
    }
};
