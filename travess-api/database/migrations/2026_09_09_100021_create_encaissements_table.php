<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Encaissements (argent entré) rattachés à un dossier, avec rapprochement
 * optionnel vers une charge (FK composite nullable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encaissements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->decimal('montant', 15, 2);
            $table->date('date');
            $table->foreignUuid('rapproche_charge_id')->nullable(); // FK composite ci-dessous
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            // Nullable : la FK composite (MATCH SIMPLE) n'est pas contrôlée quand
            // rapproche_charge_id est null. Pas de ON DELETE (NO ACTION) : on ne
            // peut pas mettre à NULL une composite (tenant_id est NOT NULL) et on
            // ne veut pas cascader la suppression d'une charge sur l'encaissement.
            // Le nettoyage global passe par le cascade tenant.
            $table->foreign(['rapproche_charge_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('charges');
        });

        RlsTenant::activer('encaissements');
    }

    public function down(): void
    {
        Schema::dropIfExists('encaissements');
    }
};
