<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Défaut d'UUID sur la clé du pivot dossier_user : belongsToMany::sync insère
 * sans fournir d'id, PostgreSQL le génère (gen_random_uuid natif depuis PG13).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE dossier_user ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dossier_user ALTER COLUMN id DROP DEFAULT');
    }
};
