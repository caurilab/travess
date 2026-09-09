<?php

declare(strict_types=1);

use App\Domains\Paiements\Enums\CiblePaiement;
use App\Domains\Paiements\Enums\OperateurPaiement;
use App\Domains\Paiements\Enums\StatutPaiement;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements Mobile Money initiés depuis le portail client.
 *
 * Référence dossiers et clients par FK composite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->foreignUuid('client_id');  // FK composite ci-dessous
            $table->decimal('montant', 15, 2);
            $table->string('cible');
            $table->string('operateur');
            $table->string('statut')->default(StatutPaiement::Initie->value);
            $table->string('ref_agregateur')->nullable();
            $table->decimal('commission_travess', 15, 2)->default(0);
            $table->string('recu_chemin')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            $table->foreign(['client_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('clients')->cascadeOnDelete();
        });

        $cibles = "'".implode("','", CiblePaiement::valeurs())."'";
        $operateurs = "'".implode("','", OperateurPaiement::valeurs())."'";
        $statuts = "'".implode("','", StatutPaiement::valeurs())."'";
        DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_cible_check CHECK (cible IN ({$cibles}))");
        DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_operateur_check CHECK (operateur IN ({$operateurs}))");
        DB::statement("ALTER TABLE paiements ADD CONSTRAINT paiements_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('paiements');
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
