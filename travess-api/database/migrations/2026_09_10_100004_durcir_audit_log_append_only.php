<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Durcissement append-only de audit_log (docs/03 §8.2).
 *
 * 1. Rupture du cascade : l'audit ne doit jamais être purgé par effet de bord
 *    d'une suppression de tenant. RESTRICT interdit par défaut la suppression
 *    d'un tenant porteur d'audit ; l'offboarding réel (purge délibérée,
 *    privilégiée et tracée) relèvera du lot « cycle de vie tenant ».
 * 2. Immutabilité par trigger, indépendante de l'ownership : le rôle applicatif
 *    étant propriétaire de la table, un simple REVOKE serait re-GRANT-able par
 *    lui-même. Le mur définitif (rôle runtime non-propriétaire, INSERT/SELECT
 *    seulement) est un durcissement d'infrastructure d'un lot ultérieur.
 *
 * ATTENTION tests : compatible RefreshDatabase (migrate:fresh = DROP … CASCADE,
 * pas DELETE). Ne pas introduire de TRUNCATE/DELETE sur audit_log ni le trait
 * DatabaseTruncation : ils seraient rejetés par le trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Rompre le cascade tenant -> audit_log.
        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_tenant_id_foreign');
        DB::statement(
            'ALTER TABLE audit_log ADD CONSTRAINT audit_log_tenant_id_foreign '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT'
        );

        // 2. Immutabilité append-only (UPDATE/DELETE/TRUNCATE interdits).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_log_append_only()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log est append-only : % interdit', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$;
        SQL);

        DB::statement(
            'CREATE TRIGGER audit_log_no_update_delete '
            .'BEFORE UPDATE OR DELETE ON audit_log '
            .'FOR EACH ROW EXECUTE FUNCTION audit_log_append_only()'
        );

        DB::statement(
            'CREATE TRIGGER audit_log_no_truncate '
            .'BEFORE TRUNCATE ON audit_log '
            .'FOR EACH STATEMENT EXECUTE FUNCTION audit_log_append_only()'
        );
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_log_no_truncate ON audit_log');
        DB::statement('DROP TRIGGER IF EXISTS audit_log_no_update_delete ON audit_log');
        DB::statement('DROP FUNCTION IF EXISTS audit_log_append_only()');

        DB::statement('ALTER TABLE audit_log DROP CONSTRAINT audit_log_tenant_id_foreign');
        DB::statement(
            'ALTER TABLE audit_log ADD CONSTRAINT audit_log_tenant_id_foreign '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE'
        );
    }
};
