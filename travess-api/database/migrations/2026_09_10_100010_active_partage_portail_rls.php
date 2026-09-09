<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Partage inter-tenant en lecture (ADR-013), prouvé EN BASE.
 *
 * Ajoute, sur les seules tables partageables (dossier + BL + conteneur +
 * parcours), une politique RLS supplémentaire « FOR SELECT » qui ouvre la
 * lecture des lignes d'un dossier explicitement octroyé au bénéficiaire courant
 * (app.portail_user_id). Elle s'AJOUTE (OR) à la politique tenant existante :
 *  - en lecture, une ligne est visible si (tenant courant) OU (dossier octroyé) ;
 *  - en écriture, seule la politique tenant s'applique → aucune écriture
 *    inter-tenant possible par ce chemin (fail-closed préservé).
 *
 * app.portail_user_id est posé par le middleware portail à partir de
 * l'utilisateur authentifié — jamais d'une entrée client (même discipline que
 * app.tenant_id).
 */
return new class extends Migration
{
    /**
     * @var list<array{table: string, predicat: string}>
     */
    private array $cibles;

    public function __construct()
    {
        $accessibles = 'SELECT app_portail_dossiers_accessibles()';

        $this->cibles = [
            ['table' => 'dossiers', 'predicat' => "id IN ({$accessibles})"],
            ['table' => 'bls', 'predicat' => "dossier_id IN ({$accessibles})"],
            ['table' => 'conteneurs', 'predicat' => "bl_id IN (SELECT b.id FROM bls b WHERE b.dossier_id IN ({$accessibles}))"],
            ['table' => 'suivi_tracking', 'predicat' => "conteneur_id IN (SELECT c.id FROM conteneurs c JOIN bls b ON b.id = c.bl_id WHERE b.dossier_id IN ({$accessibles}))"],
        ];
    }

    public function up(): void
    {
        // Ensemble des dossiers octroyés (statut actif) au bénéficiaire courant.
        // SECURITY INVOKER : la lecture d'acces_dossier reste sous sa RLS (la
        // ligne n'est visible que du bénéficiaire), donc un app.portail_user_id
        // absent renvoie l'ensemble vide → fail-closed.
        // SECURITY INVOKER explicite (défaut PostgreSQL, mais on le fige) : la
        // fonction DOIT lire acces_dossier sous la RLS de l'appelant. La passer
        // en SECURITY DEFINER (si le propriétaire était BYPASSRLS) exposerait les
        // octrois de tous les tenants — ne jamais faire.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION app_portail_dossiers_accessibles()
            RETURNS SETOF uuid
            LANGUAGE sql
            STABLE
            SECURITY INVOKER
            AS $$
                SELECT ad.dossier_id
                FROM acces_dossier ad
                WHERE ad.statut = 'actif'
                  AND ad.beneficiaire_user_id = NULLIF(current_setting('app.portail_user_id', true), '')::uuid
            $$;
        SQL);

        foreach ($this->cibles as $cible) {
            $politique = "{$cible['table']}_partage_portail";
            DB::statement(
                "CREATE POLICY {$politique} ON {$cible['table']} "
                ."FOR SELECT USING ({$cible['predicat']})"
            );
        }
    }

    public function down(): void
    {
        foreach ($this->cibles as $cible) {
            DB::statement("DROP POLICY IF EXISTS {$cible['table']}_partage_portail ON {$cible['table']}");
        }

        DB::statement('DROP FUNCTION IF EXISTS app_portail_dossiers_accessibles()');
    }
};
