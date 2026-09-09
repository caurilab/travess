<?php

declare(strict_types=1);

use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Portail\Enums\StatutDemande;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demande d'assignation d'un transitaire par un client autonome (ADR-013, 7.3b).
 *
 * Table INTER-TENANT (comme acces_dossier) : relie le workspace demandeur au
 * tenant transitaire cible → PAS de BelongsToTenant, PAS de FK composite. Le
 * tenant cible se nomme « transitaire_cible_id » et jamais « tenant_id » (sinon
 * EnsureTenantContext rejette la requête, principe n°3). Protégée par RLS :
 * lecture par le demandeur OU la cible ; insertion réservée au demandeur POUR un
 * dossier qu'il possède ET encore autonome ; décision réservée aux deux parties.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demande_assignation', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            $table->foreignUuid('tenant_demandeur_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('transitaire_cible_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('statut')->default(StatutDemande::EnAttente->value);
            $table->text('message')->nullable(); // mot du client, chiffré au repos
            $table->string('motif_refus')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expire_at');
            $table->timestamps();

            $table->index(['transitaire_cible_id', 'statut']);
            $table->index('tenant_demandeur_id');
        });

        $statuts = "'".implode("','", StatutDemande::valeurs())."'";
        DB::statement("ALTER TABLE demande_assignation ADD CONSTRAINT demande_assignation_statut_check CHECK (statut IN ({$statuts}))");

        // Une seule demande pendante par dossier (idempotence + anti-spam).
        DB::statement("CREATE UNIQUE INDEX demande_assignation_pendante_unique ON demande_assignation (dossier_id) WHERE statut = 'en_attente'");

        $tenantGuc = "NULLIF(current_setting('app.tenant_id', true), '')::uuid";
        $bypass = "coalesce(current_setting('app.bypass_rls', true), '') = 'on'";
        $autonome = PostureDossier::Autonome->value;

        $lecture = "(tenant_demandeur_id = {$tenantGuc} OR transitaire_cible_id = {$tenantGuc} OR {$bypass})";
        // Insertion : le demandeur, et seulement pour un dossier qu'il possède
        // réellement ET encore autonome (garde anti-élévation, miroir acces_dossier).
        $insertion = "((tenant_demandeur_id = {$tenantGuc} AND EXISTS (SELECT 1 FROM dossiers d WHERE d.id = dossier_id AND d.tenant_id = tenant_demandeur_id AND d.posture = '{$autonome}')) OR {$bypass})";
        // Décision/annulation : les deux parties.
        $modification = "(tenant_demandeur_id = {$tenantGuc} OR transitaire_cible_id = {$tenantGuc} OR {$bypass})";

        DB::statement('ALTER TABLE demande_assignation ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE demande_assignation FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY demande_assignation_lecture ON demande_assignation FOR SELECT USING {$lecture}");
        DB::statement("CREATE POLICY demande_assignation_insertion ON demande_assignation FOR INSERT WITH CHECK {$insertion}");
        DB::statement("CREATE POLICY demande_assignation_modification ON demande_assignation FOR UPDATE USING {$modification} WITH CHECK {$modification}");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS demande_assignation_lecture ON demande_assignation');
        DB::statement('DROP POLICY IF EXISTS demande_assignation_insertion ON demande_assignation');
        DB::statement('DROP POLICY IF EXISTS demande_assignation_modification ON demande_assignation');
        Schema::dropIfExists('demande_assignation');
    }
};
