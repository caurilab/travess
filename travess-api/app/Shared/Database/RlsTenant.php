<?php

declare(strict_types=1);

namespace App\Shared\Database;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Défense en profondeur PostgreSQL : Row-Level Security par tenant.
 *
 * Complète le scoping applicatif (TenantScope) au niveau base : même une
 * écriture/lecture qui échappe à Eloquent (insert de masse, upsert,
 * DB::table, contexte manquant) reste confinée au tenant courant.
 *
 * Mécanique :
 *  - la politique compare tenant_id au GUC de session « app.tenant_id »,
 *    positionné par TenantContext à chaque requête/traitement ;
 *  - FORCE ROW LEVEL SECURITY : la politique s'applique aussi au propriétaire
 *    de la table (le rôle applicatif) ;
 *  - un GUC absent/vide ne correspond à aucune ligne (fail-closed au niveau base) ;
 *  - une échappatoire système explicite « app.bypass_rls = on » (posée par
 *    TenantContext::runBypassed) autorise les opérations transverses auditées.
 *
 * Le modèle de menace couvert est l'erreur de développement, pas une application
 * malveillante : le rôle applicatif peut positionner les GUC, mais les chemins
 * accidentels (DB::table, insert…) ne posent jamais le bypass et restent bloqués.
 */
final class RlsTenant
{
    /**
     * Active la RLS tenant sur une table scopée.
     */
    public static function activer(string $table, string $colonne = 'tenant_id'): void
    {
        self::garderIdentifiant($table);
        self::garderIdentifiant($colonne);

        $politique = "{$table}_isolation_tenant";
        $predicat = "({$colonne} = NULLIF(current_setting('app.tenant_id', true), '')::uuid "
            ."OR coalesce(current_setting('app.bypass_rls', true), '') = 'on')";

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement(
            "CREATE POLICY {$politique} ON {$table} "
            ."USING {$predicat} WITH CHECK {$predicat}"
        );
    }

    /**
     * Désactive la RLS tenant (rollback de migration).
     */
    public static function desactiver(string $table): void
    {
        self::garderIdentifiant($table);

        $politique = "{$table}_isolation_tenant";

        DB::statement("DROP POLICY IF EXISTS {$politique} ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }

    /**
     * Garde-fou : les identifiants proviennent du code (jamais d'entrée client),
     * mais on refuse tout ce qui n'est pas un identifiant SQL simple.
     */
    private static function garderIdentifiant(string $identifiant): void
    {
        if (preg_match('/^[a-z_][a-z0-9_]*$/', $identifiant) !== 1) {
            throw new InvalidArgumentException("Identifiant SQL invalide : {$identifiant}");
        }
    }
}
