<?php

declare(strict_types=1);

use App\Domains\Dossiers\Enums\PostureDossier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posture du dossier (ADR-013, Lot 7.3). Les dossiers existants sont détenus par
 * des transitaires → « gere_par_transitaire » (défaut, qui vaut backfill). Les
 * dossiers créés par un client autonome positionnent « autonome ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table): void {
            $table->string('posture')->default(PostureDossier::GereParTransitaire->value)->after('statut');
        });

        $postures = "'".implode("','", PostureDossier::valeurs())."'";
        DB::statement("ALTER TABLE dossiers ADD CONSTRAINT dossiers_posture_check CHECK (posture IN ({$postures}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dossiers DROP CONSTRAINT IF EXISTS dossiers_posture_check');
        Schema::table('dossiers', function (Blueprint $table): void {
            $table->dropColumn('posture');
        });
    }
};
