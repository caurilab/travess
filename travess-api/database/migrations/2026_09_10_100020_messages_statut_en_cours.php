<?php

declare(strict_types=1);

use App\Domains\Correspondance\Enums\StatutMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute le statut « en_cours » (verrou d'envoi pris par le job) à la contrainte
 * CHECK de `messages`. Sépare « en file » (dispatché) de « en cours d'envoi »
 * pour une transition atomique anti double-envoi (défense audit C2).
 */
return new class extends Migration
{
    public function up(): void
    {
        $statuts = "'".implode("','", StatutMessage::valeurs())."'";
        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_statut_check');
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_statut_check CHECK (statut IN ({$statuts}))");
    }

    public function down(): void
    {
        $sans = array_filter(StatutMessage::valeurs(), static fn (string $s): bool => $s !== 'en_cours');
        $statuts = "'".implode("','", $sans)."'";
        DB::statement('ALTER TABLE messages DROP CONSTRAINT messages_statut_check');
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_statut_check CHECK (statut IN ({$statuts}))");
    }
};
