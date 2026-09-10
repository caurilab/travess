<?php

declare(strict_types=1);

use App\Domains\Correspondance\Enums\DirectionMessage;
use App\Domains\Correspondance\Enums\StatutMessage;
use App\Domains\Correspondance\Enums\TypeDemande;
use App\Domains\Messagerie\Enums\CanalMessage;
use App\Shared\Database\RlsTenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Correspondance armateur : le fil de messages d'un dossier (docs/10 §12).
 *
 * Rattaché au dossier (obligatoire) et à l'armateur (optionnel) par FK
 * composite tenant. L'auteur (agent) est hors schéma composite (ADR-004),
 * nullOnDelete. L'adresse du destinataire est FIGÉE sur le message (valeur
 * probante) même si le contact armateur change ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('dossier_id'); // FK composite ci-dessous
            $table->foreignUuid('armateur_id')->nullable(); // FK composite ci-dessous
            $table->foreignUuid('auteur_id')->nullable(); // agent émetteur (hors schéma composite)
            $table->string('direction');
            $table->string('type_demande')->nullable();
            $table->string('canal');
            $table->string('destinataire_adresse'); // PII, snapshot (ou expéditeur en entrant)
            $table->string('objet');
            $table->text('corps');
            $table->string('statut')->default(StatutMessage::EnFile->value);
            $table->string('reference_externe')->nullable();
            $table->string('erreur')->nullable();
            $table->timestamp('envoye_at')->nullable();
            $table->timestamp('recu_at')->nullable();
            $table->jsonb('meta')->default('{}');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');
            $table->index('armateur_id');
            $table->index(['dossier_id', 'created_at']); // ordre du fil

            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            // armateur_id nullable : pas de cascade (NO ACTION), nettoyage via cascade tenant.
            $table->foreign(['armateur_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('armateurs');
            // auteur (user) hors schéma composite tenant : nullOnDelete.
            $table->foreign('auteur_id')->references('id')->on('users')->nullOnDelete();
        });

        $directions = "'".implode("','", DirectionMessage::valeurs())."'";
        $types = "'".implode("','", TypeDemande::valeurs())."'";
        $canaux = "'".implode("','", CanalMessage::valeurs())."'";
        $statuts = "'".implode("','", StatutMessage::valeurs())."'";
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_direction_check CHECK (direction IN ({$directions}))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_demande_check CHECK (type_demande IS NULL OR type_demande IN ({$types}))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_canal_check CHECK (canal IN ({$canaux}))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_statut_check CHECK (statut IN ({$statuts}))");

        RlsTenant::activer('messages');
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
