<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lien optionnel notification → alerte + idempotence du dispatch : une seule
 * notification par (destinataire, canal, alerte). Un rejeu du job d'envoi ne
 * duplique pas les lignes ni les e-mails.
 *
 * FK simple vers alertes (hors schéma composite, comme users) : notifications
 * reste scopée + RLS, alerte_id est posé sous contexte tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->foreignUuid('alerte_id')->nullable()->after('destinataire_id')
                ->constrained('alertes')->nullOnDelete();

            $table->unique(['destinataire_id', 'canal', 'alerte_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique(['destinataire_id', 'canal', 'alerte_id']);
            $table->dropConstrainedForeignId('alerte_id');
        });
    }
};
