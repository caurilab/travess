<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rend DEFERRABLE (INITIALLY IMMEDIATE) les FK composites de l'agrégat dossier
 * (ADR-013, Lot 7.3b). Nécessaire à la migration de propriété : re-tenanter le
 * dossier ET ses enfants change `tenant_id` des DEUX côtés d'un FK composite
 * `(…, tenant_id) → parent(id, tenant_id)` ; aucun ordre d'UPDATE n'est cohérent
 * en vérification immédiate. On ne défère qu'au sein de la transaction de
 * migration (`SET CONSTRAINTS ALL DEFERRED`) ; hors migration, comportement
 * INCHANGÉ (INITIALLY IMMEDIATE).
 */
return new class extends Migration
{
    /**
     * @var list<array{table: string, contrainte: string}>
     */
    private array $fks = [
        ['table' => 'dossiers', 'contrainte' => 'dossiers_client_id_tenant_id_foreign'],
        ['table' => 'etapes', 'contrainte' => 'etapes_dossier_id_tenant_id_foreign'],
        ['table' => 'bls', 'contrainte' => 'bls_dossier_id_tenant_id_foreign'],
        ['table' => 'bls', 'contrainte' => 'bls_armateur_id_tenant_id_foreign'],
        ['table' => 'conteneurs', 'contrainte' => 'conteneurs_bl_id_tenant_id_foreign'],
        ['table' => 'franchises', 'contrainte' => 'franchises_conteneur_id_tenant_id_foreign'],
        ['table' => 'suivi_tracking', 'contrainte' => 'suivi_tracking_conteneur_id_tenant_id_foreign'],
        ['table' => 'documents', 'contrainte' => 'documents_dossier_id_tenant_id_foreign'],
        ['table' => 'extractions_ia', 'contrainte' => 'extractions_ia_document_id_tenant_id_foreign'],
        ['table' => 'charges', 'contrainte' => 'charges_dossier_id_tenant_id_foreign'],
        ['table' => 'encaissements', 'contrainte' => 'encaissements_dossier_id_tenant_id_foreign'],
        ['table' => 'encaissements', 'contrainte' => 'encaissements_rapproche_charge_id_tenant_id_foreign'],
        ['table' => 'honoraires', 'contrainte' => 'honoraires_dossier_id_tenant_id_foreign'],
        ['table' => 'missions_transport', 'contrainte' => 'missions_transport_dossier_id_tenant_id_foreign'],
        ['table' => 'missions_transport', 'contrainte' => 'missions_transport_bon_livraison_doc_id_tenant_id_foreign'],
        ['table' => 'alertes', 'contrainte' => 'alertes_dossier_id_tenant_id_foreign'],
        ['table' => 'alertes', 'contrainte' => 'alertes_conteneur_id_tenant_id_foreign'],
    ];

    public function up(): void
    {
        foreach ($this->fks as $fk) {
            DB::statement("ALTER TABLE {$fk['table']} ALTER CONSTRAINT {$fk['contrainte']} DEFERRABLE INITIALLY IMMEDIATE");
        }
    }

    public function down(): void
    {
        foreach ($this->fks as $fk) {
            DB::statement("ALTER TABLE {$fk['table']} ALTER CONSTRAINT {$fk['contrainte']} NOT DEFERRABLE");
        }
    }
};
