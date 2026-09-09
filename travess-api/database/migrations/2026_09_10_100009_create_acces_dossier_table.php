<?php

declare(strict_types=1);

use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Table de partage inter-tenant (ADR-013). Relie le tenant propriétaire d'un
 * dossier à un compte bénéficiaire (client ou transitaire) d'un autre tenant.
 *
 * Exception documentée à ADR-004 : PAS de FK composite (id, tenant_id) — la
 * table relie DEUX tenants — et pas de TenantScope. Sa protection est une
 * politique RLS dédiée :
 *  - lecture : bénéficiaire (via app.portail_user_id) OU tenant propriétaire OU
 *    tenant bénéficiaire (via app.tenant_id) OU bypass système ;
 *  - écriture : tenant propriétaire seulement (OU bypass) — on ne s'octroie pas
 *    un accès au dossier d'autrui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acces_dossier', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            $table->foreignUuid('tenant_proprietaire_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('beneficiaire_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('beneficiaire_tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('niveau');
            $table->string('statut')->default(StatutAcces::EnAttente->value);
            $table->string('origine');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['beneficiaire_user_id', 'statut']);
            $table->index('dossier_id');
            // Un bénéficiaire n'a qu'un accès (courant) par dossier.
            $table->unique(['dossier_id', 'beneficiaire_user_id']);
        });

        $niveaux = "'".implode("','", NiveauAcces::valeurs())."'";
        $statuts = "'".implode("','", StatutAcces::valeurs())."'";
        $origines = "'".implode("','", OrigineAcces::valeurs())."'";
        DB::statement("ALTER TABLE acces_dossier ADD CONSTRAINT acces_dossier_niveau_check CHECK (niveau IN ({$niveaux}))");
        DB::statement("ALTER TABLE acces_dossier ADD CONSTRAINT acces_dossier_statut_check CHECK (statut IN ({$statuts}))");
        DB::statement("ALTER TABLE acces_dossier ADD CONSTRAINT acces_dossier_origine_check CHECK (origine IN ({$origines}))");

        $tenantGuc = "NULLIF(current_setting('app.tenant_id', true), '')::uuid";
        $portailGuc = "NULLIF(current_setting('app.portail_user_id', true), '')::uuid";
        $bypass = "coalesce(current_setting('app.bypass_rls', true), '') = 'on'";

        $lecture = "(beneficiaire_user_id = {$portailGuc} "
            ."OR tenant_proprietaire_id = {$tenantGuc} "
            ."OR beneficiaire_tenant_id = {$tenantGuc} "
            ."OR {$bypass})";

        // Écriture réservée au tenant propriétaire ET seulement pour un dossier
        // qu'il possède réellement. Sans la vérification d'appartenance, un
        // compte pourrait s'auto-octroyer l'accès à un dossier d'autrui en posant
        // simplement tenant_proprietaire_id = son propre tenant (élévation).
        $possedeLeDossier = 'EXISTS (SELECT 1 FROM dossiers d '
            .'WHERE d.id = dossier_id AND d.tenant_id = tenant_proprietaire_id)';
        $ecriture = "((tenant_proprietaire_id = {$tenantGuc} AND {$possedeLeDossier}) OR {$bypass})";

        DB::statement('ALTER TABLE acces_dossier ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE acces_dossier FORCE ROW LEVEL SECURITY');

        // Lecture et écriture séparées : une politique « FOR ALL » unique
        // laisserait le DELETE ne vérifier que USING (large) — un bénéficiaire
        // pourrait alors supprimer son octroi (ou celui d'un pair de son
        // workspace) et contourner la révocation douce. En scindant, seul le
        // propriétaire écrit (INSERT/UPDATE/DELETE), tout le monde de concerné lit.
        DB::statement("CREATE POLICY acces_dossier_lecture ON acces_dossier FOR SELECT USING {$lecture}");
        DB::statement("CREATE POLICY acces_dossier_ecriture ON acces_dossier FOR ALL USING {$ecriture} WITH CHECK {$ecriture}");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS acces_dossier_lecture ON acces_dossier');
        DB::statement('DROP POLICY IF EXISTS acces_dossier_ecriture ON acces_dossier');
        Schema::dropIfExists('acces_dossier');
    }
};
