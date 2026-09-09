<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Le franc CFA (XOF) n'a pas de sous-unité : les montants de franchise sont des
 * entiers. On passe montant_en_cours / montant_menacant en numeric(15,0).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE franchises ALTER COLUMN montant_en_cours TYPE numeric(15,0)');
        DB::statement('ALTER TABLE franchises ALTER COLUMN montant_menacant TYPE numeric(15,0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE franchises ALTER COLUMN montant_en_cours TYPE numeric(15,2)');
        DB::statement('ALTER TABLE franchises ALTER COLUMN montant_menacant TYPE numeric(15,2)');
    }
};
