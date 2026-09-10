<?php

declare(strict_types=1);

namespace App\Domains\Portail\Services;

use App\Domains\Armateurs\Models\Armateur;
use App\Domains\Audit\Services\Auditeur;
use App\Domains\Dossiers\Enums\PostureDossier;
use App\Domains\Dossiers\Models\Dossier;
use App\Domains\Dossiers\Support\GenerateurReference;
use App\Domains\Portail\Enums\NiveauAcces;
use App\Domains\Portail\Enums\OrigineAcces;
use App\Domains\Portail\Enums\StatutAcces;
use App\Domains\Portail\Models\AccesDossier;
use App\Domains\Tenancy\Models\Client;
use App\Domains\Tenancy\Models\Tenant;
use App\Shared\Context\TenantContext;
use App\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Migration de propriété d'un dossier (ADR-013, Lot 7.3b) — l'opération la plus
 * risquée du lot. Re-tenante le dossier ET tout son agrégat du workspace client
 * vers le tenant transitaire, dans UNE transaction, sous verrou et bypass borné
 * à ce dossier, avec FK composites déférées. À l'issue, le client garde un
 * acces_dossier « limite » (BL + parcours) ; il perd tout le reste.
 *
 * Prérequis (à garantir par l'appelant) : le dossier est « autonome » et détenu
 * par le tenant courant (client). Doit s'exécuter DANS une transaction ouverte
 * par l'appelant après SET CONSTRAINTS ALL DEFERRED (voir AccepterAssignation).
 */
final class MigrerProprieteDossier
{
    /**
     * Enfants de l'agrégat : table => clause de rattachement au dossier (:d).
     * bls et dossiers sont traités à part (armateur / racine). Liste EXHAUSTIVE
     * (test de complétude anti-orphelin) — plusieurs seront vides pour un dossier
     * autonome, l'engin les traite génériquement.
     *
     * @var array<string, string>
     */
    private const ENFANTS = [
        'etapes' => 'dossier_id = ?',
        'documents' => 'dossier_id = ?',
        'charges' => 'dossier_id = ?',
        'honoraires' => 'dossier_id = ?',
        'encaissements' => 'dossier_id = ?',
        'missions_transport' => 'dossier_id = ?',
        'alertes' => 'dossier_id = ?',
        // Correspondance armateur : suit le dossier vers son nouveau propriétaire
        // (comme documents/alertes), jamais orpheline chez l'ancien tenant.
        'messages' => 'dossier_id = ?',
        'conteneurs' => 'bl_id IN (SELECT id FROM bls WHERE dossier_id = ?)',
        'franchises' => 'conteneur_id IN (SELECT c.id FROM conteneurs c JOIN bls b ON b.id = c.bl_id WHERE b.dossier_id = ?)',
        'suivi_tracking' => 'conteneur_id IN (SELECT c.id FROM conteneurs c JOIN bls b ON b.id = c.bl_id WHERE b.dossier_id = ?)',
        'extractions_ia' => 'document_id IN (SELECT id FROM documents WHERE dossier_id = ?)',
    ];

    /**
     * Tables rattachées à un dossier qui NE migrent PAS et NE doivent PAS exister
     * pour un dossier migrable (agents propres au tenant ; paiements = sous-lot
     * 7.5 non traité). La migration refuse si l'une contient des lignes, plutôt
     * que de les abandonner chez le client (anti-orphelin). Le test de complétude
     * (piloté par le schéma) impose que toute table de l'agrégat soit ici ou dans
     * ENFANTS.
     *
     * @var list<string>
     */
    private const GARDEES = ['dossier_user', 'paiements', 'invitation_portail'];

    /**
     * Classification des tables de l'agrégat (pour le test de complétude).
     *
     * @return array{enfants: list<string>, speciales: list<string>, gardees: list<string>}
     */
    public static function classification(): array
    {
        return [
            'enfants' => array_keys(self::ENFANTS),
            'speciales' => ['dossiers', 'bls'], // racine + BL (remap armateur)
            'gardees' => self::GARDEES,
        ];
    }

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly GenerateurReference $reference,
        private readonly Auditeur $auditeur,
    ) {}

    /**
     * @return array{ancienne_reference: string, nouvelle_reference: string}
     */
    public function executer(Dossier $dossier, Tenant $source, Tenant $transitaire): array
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('MigrerProprieteDossier doit s’exécuter dans une transaction.');
        }

        // Tenant source (client) explicite : l'engin ne dépend pas du tenant
        // courant (l'acceptation a lieu dans le contexte du transitaire).
        $tenantClient = $source->id;
        $dossierId = (string) $dossier->getKey();
        $ancienneReference = $dossier->reference;

        // Sérialise la migration de CE dossier et défère les FK composites le
        // temps du re-tenant (état transitoirement incohérent des deux côtés).
        DB::selectOne('select pg_advisory_xact_lock(hashtextextended(?, 0))', [$dossierId]);

        // Anti-orphelin : refuse si des tables non migrables sont attachées
        // (elles resteraient chez le client → fuite). Impossible pour un dossier
        // autonome aujourd'hui ; garde explicite pour les évolutions.
        $this->refuserSiNonMigrable($dossierId);

        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        // Préparations en contexte T_transit (fiche client, armateurs, référence)
        // — hors bypass, au privilège minimal (le transitaire crée chez lui).
        [$clientCibleId, $mapArmateurs, $nouvelleReference, $beneficiaireUserId] = $this->tenant->pour(
            $transitaire->id,
            fn (): array => [
                $this->ficheClientChezTransitaire($dossier),
                $this->mapArmateurs($dossierId),
                $this->reference->suivante($dossier->sens),
                $this->beneficiaireDuTenantClient($tenantClient),
            ],
        );

        // Re-tenant proprement dit : écriture inter-tenant → bypass borné à ce dossier.
        $this->tenant->runBypassed(function () use ($dossierId, $transitaire, $clientCibleId, $mapArmateurs, $nouvelleReference): void {
            // Racine.
            DB::update(
                'UPDATE dossiers SET tenant_id = ?, client_id = ?, reference = ?, posture = ? WHERE id = ?',
                [$transitaire->id, $clientCibleId, $nouvelleReference, PostureDossier::GereParTransitaire->value, $dossierId],
            );

            // BL : re-tenant + remap de l'armateur vers celui du transitaire.
            foreach ($mapArmateurs as $blId => $armateurId) {
                DB::update('UPDATE bls SET tenant_id = ?, armateur_id = ? WHERE id = ?', [$transitaire->id, $armateurId, $blId]);
            }

            // Enfants génériques.
            foreach (self::ENFANTS as $table => $rattachement) {
                DB::update("UPDATE {$table} SET tenant_id = ? WHERE {$rattachement}", [$transitaire->id, $dossierId]);
            }
        });

        // Octroi « limite » au client + audit, en contexte T_transit (le
        // propriétaire est désormais le transitaire → RLS d'écriture OK).
        $this->tenant->pour($transitaire->id, function () use ($dossierId, $transitaire, $tenantClient, $beneficiaireUserId, $ancienneReference, $nouvelleReference): void {
            AccesDossier::create([
                'dossier_id' => $dossierId,
                'tenant_proprietaire_id' => $transitaire->id,
                'beneficiaire_user_id' => $beneficiaireUserId,
                'beneficiaire_tenant_id' => $tenantClient,
                'niveau' => NiveauAcces::Limite->value,
                'statut' => StatutAcces::Actif->value,
                'origine' => OrigineAcces::AssignationClient->value,
            ]);

            $this->auditeur->enregistrer('dossier', $dossierId, 'dossier.migre_entrant', null, [
                'depuis_tenant' => $tenantClient,
                'ancienne_reference' => $ancienneReference,
                'nouvelle_reference' => $nouvelleReference,
            ]);
        });

        // Trace côté client (tenant émetteur).
        $this->tenant->pour($tenantClient, function () use ($dossierId, $transitaire, $ancienneReference): void {
            $this->auditeur->enregistrer('dossier', $dossierId, 'dossier.migre_sortant', null, [
                'vers_tenant' => $transitaire->id,
                'ancienne_reference' => $ancienneReference,
            ]);
        });

        // Valide MAINTENANT les FK déférées, sur l'état final cohérent (fail-fast
        // et vérifiable en test, où la transaction externe n'est jamais commitée).
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');

        return ['ancienne_reference' => $ancienneReference, 'nouvelle_reference' => $nouvelleReference];
    }

    private function refuserSiNonMigrable(string $dossierId): void
    {
        $bloque = $this->tenant->runBypassed(function () use ($dossierId): bool {
            foreach (self::GARDEES as $table) {
                if (DB::table($table)->where('dossier_id', $dossierId)->exists()) {
                    return true;
                }
            }

            return false;
        });

        if ($bloque) {
            throw new HttpException(409, 'Dossier non éligible à la migration (collaborateurs ou paiements attachés).');
        }
    }

    /**
     * Fiche client (donneur d'ordre) dans le tenant du transitaire, copiée de la
     * fiche self du client. Créée neuve, marquée origine assignation.
     */
    private function ficheClientChezTransitaire(Dossier $dossier): string
    {
        $nom = $this->tenant->runBypassed(fn (): ?string => Client::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($dossier->client_id)
            ->value('nom'));

        return (string) Client::create(['nom' => $nom ?? 'Client', 'est_self' => false])->getKey();
    }

    /**
     * Pour chaque BL du dossier, l'armateur cible (par nom) dans le tenant du
     * transitaire (créé minimal si absent). Retourne bl_id => armateur_id cible.
     *
     * @return array<string, string>
     */
    private function mapArmateurs(string $dossierId): array
    {
        /** @var list<object{id: string, armateur_id: string, nom: string}> $bls */
        $bls = $this->tenant->runBypassed(fn (): array => DB::select(
            'SELECT b.id, b.armateur_id, a.nom FROM bls b JOIN armateurs a ON a.id = b.armateur_id WHERE b.dossier_id = ?',
            [$dossierId],
        ));

        $parNom = [];
        $map = [];

        foreach ($bls as $bl) {
            $nom = (string) $bl->nom;
            $parNom[$nom] ??= (string) Armateur::firstOrCreate(['nom' => $nom], ['trackable' => false])->getKey();
            $map[(string) $bl->id] = $parNom[$nom];
        }

        return $map;
    }

    /**
     * Le compte client bénéficiaire de l'octroi limite = l'utilisateur (rôle
     * client) du workspace émetteur.
     */
    private function beneficiaireDuTenantClient(string $tenantClient): string
    {
        $userId = $this->tenant->runBypassed(fn (): ?string => DB::table('users')
            ->where('tenant_id', $tenantClient)
            ->orderBy('created_at')
            ->value('id'));

        if ($userId === null) {
            throw new RuntimeException('Aucun compte client pour le workspace à migrer.');
        }

        return (string) $userId;
    }
}
