<?php

declare(strict_types=1);

use App\Domains\Messagerie\Enums\CanalMessage;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\StatutInvitation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invitations d'onboarding portail (ADR-013). Émises par un transitaire pour
 * partager un dossier à un client sans compte.
 *
 * Table SCOPÉE par l'émetteur (contrairement à acces_dossier) : BelongsToTenant
 * + FK composites. Le token n'est stocké que HACHÉ (token brut jamais persisté
 * ni journalisé). Le destinataire (PII) est chiffré au repos.
 *
 * RLS : lecture par l'émetteur (tenant courant) OU, sur le chemin de réclamation
 * PUBLIC, par le token présenté (GUC app.invitation_token_hash, borné à une
 * ligne, posé par le seul middleware public). L'écriture reste à l'émetteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitation_portail', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->foreignUuid('dossier_id');
            $table->foreignUuid('client_id');
            $table->string('canal');
            $table->text('destinataire_chiffre'); // PII chiffrée au repos
            $table->string('niveau')->default(NiveauAcces::Limite->value);
            $table->string('statut')->default(StatutInvitation::Emise->value);
            $table->timestamp('expire_at');
            $table->boolean('usage_unique')->default(true);
            $table->unsignedInteger('tentatives')->default(0);
            $table->uuid('consumed_by_user_id')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('dossier_id');

            // FK composites (défense en profondeur, ADR-004) : dossier et client
            // du même tenant que l'émetteur.
            $table->foreign(['dossier_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('dossiers')->cascadeOnDelete();
            $table->foreign(['client_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])->on('clients')->cascadeOnDelete();
        });

        $canaux = "'".implode("','", CanalMessage::valeurs())."'";
        $niveaux = "'".implode("','", NiveauAcces::valeurs())."'";
        $statuts = "'".implode("','", StatutInvitation::valeurs())."'";
        DB::statement("ALTER TABLE invitation_portail ADD CONSTRAINT invitation_portail_canal_check CHECK (canal IN ({$canaux}))");
        DB::statement("ALTER TABLE invitation_portail ADD CONSTRAINT invitation_portail_niveau_check CHECK (niveau IN ({$niveaux}))");
        DB::statement("ALTER TABLE invitation_portail ADD CONSTRAINT invitation_portail_statut_check CHECK (statut IN ({$statuts}))");

        $tenantGuc = "NULLIF(current_setting('app.tenant_id', true), '')::uuid";
        $tokenGuc = "NULLIF(current_setting('app.invitation_token_hash', true), '')";
        $bypass = "coalesce(current_setting('app.bypass_rls', true), '') = 'on'";

        // Lecture : émetteur (tenant) OU réclamation publique bornée au token
        // présenté (une seule ligne) OU bypass système (provisionnement).
        $lecture = "(tenant_id = {$tenantGuc} OR token_hash = {$tokenGuc} OR {$bypass})";
        // Écriture : émetteur (ou bypass système pour la consommation).
        $ecriture = "(tenant_id = {$tenantGuc} OR {$bypass})";

        DB::statement('ALTER TABLE invitation_portail ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE invitation_portail FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY invitation_portail_lecture ON invitation_portail FOR SELECT USING {$lecture}");
        DB::statement("CREATE POLICY invitation_portail_ecriture ON invitation_portail FOR ALL USING {$ecriture} WITH CHECK {$ecriture}");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS invitation_portail_lecture ON invitation_portail');
        DB::statement('DROP POLICY IF EXISTS invitation_portail_ecriture ON invitation_portail');
        Schema::dropIfExists('invitation_portail');
    }
};
