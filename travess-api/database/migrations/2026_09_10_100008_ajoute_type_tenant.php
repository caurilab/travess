<?php

declare(strict_types=1);

use App\Domains\Tenancy\Enums\TypeTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-013 : un tenant est soit un transitaire (agence), soit un client
 * (workspace léger du portail). Les tenants existants sont des transitaires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('type')->default(TypeTenant::Transitaire->value)->after('nom');
        });

        $types = "'".implode("','", TypeTenant::valeurs())."'";
        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_type_check CHECK (type IN ({$types}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_type_check');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
