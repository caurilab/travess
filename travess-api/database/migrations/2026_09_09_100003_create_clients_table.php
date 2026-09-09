<?php

declare(strict_types=1);

use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients (donneurs d'ordre) d'un tenant. Modèle scopé par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('nom');
            $table->string('contact')->nullable();
            $table->jsonb('canaux')->default('{}'); // numéro WhatsApp, email…
            $table->timestamps();

            $table->index('tenant_id');

            // Cible pour les FK composites des futures tables filles :
            // FOREIGN KEY (client_id, tenant_id) REFERENCES clients(id, tenant_id).
            // Rend structurellement impossible un enfant rattaché à un client
            // d'un autre tenant. Patron à répliquer sur toute table parente scopée.
            $table->unique(['id', 'tenant_id']);
        });

        // Défense en profondeur base : RLS tenant (cf. RlsTenant).
        RlsTenant::activer('clients');
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
