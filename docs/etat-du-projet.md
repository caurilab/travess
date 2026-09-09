# Travess — État du projet

> Point de situation au démarrage. À tenir à jour au fil des lots.

---

## Décidé et figé

- **Nom** : Travess (transit + vessel). **Domaine** : travess.ci.
- **Périmètre V1** : tout (cœur métier, IA d'extraction, tracking auto, Mobile Money, portail client), livré en lots séquencés.
- **Stack** : Laravel 13 (PHP 8.3+, Laravel AI SDK) · PostgreSQL · React 19 (React Compiler) · Electron (réutilise le web) · React Native (natif) · Redis/queues.
- **Monorepo** : pnpm workspaces + Turborepo ; `travess-api`, `travess-web`, `travess-mobile`, `travess-desktop`, `packages/*`.
- **Multi-tenant** : base unique, isolation par `tenant_id`.
- **Tracking** : JSONCargo mutualisé + fallback IMAP ; scheduler intelligent + plafond de sécurité.
- **Pas de reprise de données** : le prototype HTML est une maquette de validation, pas un système en production.

## Produit à ce stade

- Prototype HTML fonctionnel servant de référence métier (à ne pas reprendre en l'état côté UI).
- Documentation produit et technique complète (ce dossier `docs/`).
- Prompt de démarrage pour l'agent de code (`PROMPT-DEMARRAGE.md`).
- Sous-agents de développement définis (`.claude/agents/`).

## Avancement — Lot 0 (Fondations)

> En cours. Base de dev/test sur PostgreSQL (Docker configuré ; validé localement, Docker non disponible sur le poste courant). Décisions consignées en ADR-002 à ADR-005.

**Fait et vérifié :**
- Monorepo pnpm + Turborepo opérationnel ; git initialisé (branche `main`).
- `travess-api` : Laravel 13.31 en API pure, découpage `app/Domains/*` (12 domaines) + `app/Shared`, routes `/api/v1` chargées par domaine.
- Multi-tenant **défense en profondeur, fail-closed** (ADR-004) : `TenantContext` (scoped), `TenantScope`, trait `BelongsToTenant`, middleware `EnsureTenantContext`, **Row-Level Security PostgreSQL** + FK composites `UNIQUE(id, tenant_id)`. Audit sécurité passé (bloquants traités).
- **Auth (0.c)** : Sanctum (jetons Bearer, expiration 1 j), Fortify **2FA TOTP** (activer/confirmer/désactiver, défi au login, codes de récupération à usage unique, anti-rejeu), endpoints `/auth/login|logout|me|refresh`. **Garde-fous `User`** (policy + route-model binding scopé fail-closed + tests d'isolation dédiés). Audit auth passé, durcissements appliqués : anti-énumération par timing, plafond login par IP, réauth pour désactiver le 2FA, non-réexposition d'un secret 2FA confirmé.
- **Schéma complet (0.d)** : les 19 tables restantes de `docs/07` (armateurs, dossiers, étapes, BL, conteneurs, franchises, tracking, documents, extraction IA, charges, encaissements, honoraires, transport, alertes, notifications, paiements, consommation, audit) — 18 modèles, 21 enums, 18 factories, RLS + FK composites partout. Test anti-régression garantissant `BelongsToTenant` sur tout modèle scopé.
- **ISO 6346** : port TS (`packages/shared-core`) + port PHP (`app/Domains/Conteneurs/Support`), parité garantie par vecteurs partagés (ADR-003).
- `packages/shared-types` (DTO/enums miroir), `packages/ui` (jetons + statuts normalisés, direction corail).
- **CI & qualité (0.g)** : `.github/workflows/ci.yml` (job JS : typecheck/test/build ; job API : Postgres+Redis, migrate, Larastan, Pint, tests). **Pint** (style) et **Larastan niveau 5** verts.
- Tests : **56 PHP** (étanchéité/RLS, auth+2FA, isolation users, parité ISO 6346) + **29 TS**, tous verts. Typecheck TS et build des 3 packages OK.

**Reste à faire pour clore le Lot 0 :**
- Design system (0.f) : composants de base (`Button`, `Input`, `Badge`, `Card`, `Table`), à développer contre `travess-web` au Lot 1 (jetons déjà livrés).

## Dette technique identifiée (issue des audits sécurité)

- **Porteur de contexte tenant pour les jobs/queues** : à livrer avant le premier traitement en file (Lot 2), sinon fail-closed en file ou bypass dangereux.
- **Contrôle du statut du tenant** (actif/suspendu) dans le middleware : à ajouter au lot facturation.
- **Journalisation des `runBypassed`** (traçabilité des accès système/éditeur).
- **Abilities de jeton** (séparation portail/agent) et **whitelist du `role`** à l'arrivée du CRUD utilisateurs : à traiter au lot Portail.
- **Larastan** : monter du niveau 5 vers 6+ progressivement (annotations génériques).

## Avancement — Lot 1 (Cœur dossier, surface agent)

> En cours. Structure cadrée par l'architecte (ADR-006/007/008), arbitrages produit validés : référence auto par tenant, clôture manuelle auditée, workflow défaut-code + snapshot, filtres via spatie/laravel-query-builder.

**Fait et vérifié — slice Dossiers :**
- CRUD dossiers (`POST/GET/PATCH /dossiers`, `/{id}`), liste **filtrable** (sens, statut, client, agent) et paginée, détail imbriqué (client + étapes + agents, résumés finances/transport à forme figée).
- **Référence auto** par tenant (IMP-2026-0001 / EXP-…, verrou consultatif + UNIQUE(tenant, reference)).
- **Instanciation du workflow** à la création (snapshot des étapes par défaut selon le sens, `date_prevue` dérivée du cumul des SLA).
- **Clôture** manuelle auditée (`POST /{id}/cloturer`), **assignation** des agents (`PUT /{id}/agents`, pivot scopé), **journal d'audit** (`GET /{id}/audit`, curseur).
- **Audit** systématique via `Auditeur` appelé dans les Actions (transactionnel) ; **policies** par rôle (gérant/agent RW, comptable lecture).
- Route-model binding **tenant-sûr** généralisé au trait `BelongsToTenant` (RLS levée pour la seule résolution, filtre tenant explicite → 404 inter-tenant).
- DTO miroirs ajoutés à `packages/shared-types` (dossier, étape, audit, enums).
- **10 tests Dossiers** verts (création+workflow, référence incrémentale, filtres, détail, mise à jour, clôture, assignation, audit, isolation, RBAC). Total **71 PHP + 33 TS**, Pint + Larastan 0.

**Fait et vérifié — slices Étapes, Conteneurs/BL, Documents :**
- Étapes : mise à jour (statut/SLA/dates, « fait » horodaté), réordonnancement contrôlé.
- Conteneurs & BL : création BL (navire par IMO), conteneur avec contrôle serveur ISO 6346 + normalisation, mise à jour statut, `GET /conteneurs/valider`.
- Documents : dépôt multipart (stockage objet, MIME deviné, nettoyage si échec), consultation, téléchargement (sans IA).
- **Durcissement append-only `audit_log`** (audité) : trigger UPDATE/DELETE/TRUNCATE + FK `ON DELETE RESTRICT` (ADR-009) — l'audit survit au tenant. Garde-fou d'architecture « mutation ⇒ Action ».
- Correctifs de revue : `BlPolicy` (contrôle de rôle), parité DTO (`conteneur`/`bl`/`document` dans shared-types), rôles assignables restreints, générateur de référence robuste (>9999).
- **Lot 1 complet** : 89 tests PHP + 33 TS verts, Pint + Larastan 0. Contrat docs/10 mis à jour.

## Dette technique identifiée (revues du Lot 1)

- **Audit des événements d'authentification** (activation/désactivation 2FA, ouverture/révocation de session) : périmètre à confirmer vs docs/03 §8.2, puis tracer (M-1).
- **Séparation des rôles PostgreSQL** : un rôle runtime non-propriétaire de `audit_log` (INSERT/SELECT seulement) = le vrai « mur » d'immutabilité — durcissement d'infra + chemin d'offboarding tenant (purge tracée).
- **Audit système hors requête HTTP** : fournir un `tenant_id` explicite (le hook `creating` ne le pose pas sous `runBypassed`) avant les lots tracking/IA (F-1).
- **Filtrage des secrets** dans `avant`/`apres` de l'audit avant le lot paiement (F-2).
- **Raffinement RBAC** « agent = dossiers assignés » (au Lot 1, l'isolation dure est le tenant).
- **Résumés finances/transport** du détail dossier : placeholders (0/null) à remplacer par les vraies valeurs aux lots 3/4+.
- **Confirmation de remise e-mail** : la notification e-mail reste « en_attente » (mise en file) ; un listener `MessageSent` la passera à « envoyé » (raffinement).
- **`/alertes/preferences`** (édition des canaux de notification) : reporté (les préférences sont déjà lues par l'envoi).
- **Gel exact du `montant_en_cours`** à la sortie : nécessiterait la date de sortie réelle du conteneur (aujourd'hui : dernière valeur active, ou estimation courante). À affiner avec le tracking (Lot 4).

## À faire — prochaines actions

1. Confirmer auprès de JSONCargo : **HTTPS** de la base URL, et statut **Grimaldi**.
2. Choisir l'**agrégateur Mobile Money** et cadrer le volet réglementaire avec un conseil juridique local.
3. Lancer le **Lot 0** (fondations monorepo + API + auth + multi-tenant + modèle de données + design system).
4. Souscrire **JSONCargo Navigator** et instrumenter la consommation dès le Lot 4.
5. **Ingestion IA — à sécuriser avant prod** (avec l'intégrateur externe) : provisionner la **clé Anthropic** dans `.env` (jamais dans le dépôt, jamais journalisée), **verrouiller la non-rétention** côté fournisseur (zero data retention sur le compte Anthropic), et **confirmer l'identifiant de modèle** (`IA_MODELE`, aujourd'hui `claude-sonnet-5`) et le fournisseur. Sans clé, le pipeline tourne sur l'adaptateur factice.

## Avancement — Lot 2 (Surestaries & alertes)

> Fonctionnellement complet (audit sécurité jobs/scheduler + revue en cours). Décisions en ADR-010/011.

**Fait et vérifié :**
- **Calcul des franchises** (surestaries/détention) dans `shared-core`, **parité PHP↔TS** par vecteurs partagés (barème progressif, jours calendaires, montants entiers XOF, gel à la sortie).
- **Franchises** persistées/recalculées (service `RecalculFranchise`, port unique) ; Actions `DefinirFranchise`/`AjusterFranchise` (auditées) ; endpoints `GET/POST /conteneurs/{id}/franchises`, `PATCH /franchises/{id}` (recalcul frais en lecture, sans écriture).
- **Porteur de contexte tenant en file** (`TenantContext::pour`, `JobTenantScoped`) — dette F-1 résorbée, étanchéité en file testée.
- **Moteur d'alertes** : job `RafraichirSurestaries` par tenant + commande `surestaries:rafraichir` planifiée quotidiennement ; génération idempotente J-3/J-1/J0 ; `GET /alertes`, `PATCH /alertes/{id}`.
- **Répartition multi-canal découplée** : adaptateurs `CanalEnvoi` (email réel en file + canaux différés stub), job `EnvoyerAlerte`, journal `notification` + `canaux_envoyes`.
- **Tableaux de bord** : `GET /dashboard/argent-en-feu` (menaçant cumulé + conteneurs à risque) et `/dashboard/surestaries-evitees` (métrique basée sur preuve).
- **109 tests PHP + parité TS** verts, Pint + Larastan 0.

## Avancement — Lot 5 (Ingestion documentaire par IA)

> Terminé et validé. Décision consignée en ADR-012. Audit sécurité, revue et testeur passés.

**Fait et vérifié :**
- **Contrat `ExtracteurDocument`** (principe n°9) avec deux adaptateurs : `ExtracteurFactice` (déterministe, sans réseau, **driver par défaut**) et `ExtracteurLaravelAi` (Claude via `laravel/ai` v0.11.2, sortie structurée `{valeur, confiance, zone_source}`, document en base64 inline). Driver choisi par `config('ia.driver')`.
- **DTO neutres** (`DocumentAExtraire`, `SchemaExtraction`, `ChampExtrait`, `ResultatExtraction`) et **schémas par type de document** dans `config/ia.php` via `RegistreSchemas` (BL prioritaire, plus facture de charges, DO, déclaration douane, bon de livraison).
- **Pipeline en file** (principe n°4) : Action `LancerExtraction` (opt-in tenant, réservation **atomique** du quota, verrou consultatif `pg_advisory_xact_lock` anti double-lancement, idempotence, dispatch `afterCommit`) → job `ExtraireDocument` (`JobTenantScoped`, idempotent, re-vérifie l'opt-in, `report($e)`, audit, libère l'unité en cas d'échec) → Action `ValiderExtraction` (validation humaine sous `lockForUpdate`, application via `ApplicateurExtraction` par type ; `ApplicateurBl` crée BL + conteneurs).
- **L'IA propose, l'humain valide** (principe n°5) : aucune écriture au dossier sans validation humaine explicite.
- **Décompte de consommation tenant-scopé** (`DecompteConsommationIa`) : réservation / libération / enregistrement du coût, incrément conditionnel atomique borné par `quota_ia_mensuel`.
- **Endpoints** : `POST /documents/{document}/extraction`, `GET /documents/{document}/extraction`, `POST /extractions/{extraction}/validation` ; autorisation par `DocumentPolicy::extraire` (rôles en écriture).
- **Testable sans clé API** : l'adaptateur factice par défaut exerce tout le pipeline de bout en bout.
- **136 tests PHP** verts, Pint + Larastan niveau 5 à 0 erreur.

## Dette technique identifiée (Lot 5 — ingestion IA)

- **Résolution armateur** : l'IA extrait le **nom** d'armateur (indicatif) ; l'application au dossier exige `armateur_id` (annuaire), fourni par l'humain à la validation. Le rapprochement automatique nom → id reste à faire.
- **Confidentialité** : la non-rétention côté fournisseur dépend de la configuration du compte Anthropic (zero data retention), non imposée par le code — à verrouiller avec l'intégrateur externe avant prod.
- **Schéma « liste » générique** → array de chaînes ; l'extraction structurée des conteneurs (objets `{numero, type}`) reste à affiner.
- **Reprise sur échec** : le job n'a pas de retry configuré (il attrape lui-même `Throwable`) ; reprise manuelle. Le motif d'échec n'est pas persisté (pas de colonne erreur) — évolution possible.
- **Identifiant de modèle** (`IA_MODELE=claude-sonnet-5`) et **fournisseur** à confirmer avec l'intégrateur externe.

## Décisions produit à intégrer (cadrage en cours)

> Modèle client/transitaire & partage inter-tenant — cadrage en cours (impacte l'isolation stricte d'ADR-004). Conception délibérée à faire au lot Portail/onboarding (architecte + auditeur + nouvel ADR). Non tranché : le modèle de tenant exact (transitaire = tenant + client compte partagé, vs deux espaces). Le travail interne au transitaire (Lots 0–2) reste valide quel que soit le modèle.

- **Deux types de comptes, non cumulables** : un compte est **soit** transitaire **soit** client (on abandonne l'idée d'un compte bi-rôle commutable).
- **Le client peut créer un dossier** depuis son compte, saisir toutes les infos (BL…). Le champ **« assigner un transitaire » est facultatif**, choisi dans un **annuaire de tous les transitaires de la plateforme** (recherche) → partage inter-tenant.
- **Deux flux d'assignation** (symétriques) : client → assigne un transitaire → notification → le transitaire **accepte** et enrichit ; ou transitaire → crée le dossier → **invite** son client (lien) → le client valide et accède.
- **Visibilité selon la posture** :
  - **Transitaire** : voit **tout** le dossier.
  - **Client avec transitaire assigné** : vue **restreinte** — contenu du/des BL + **parcours** (avancement). Pas de finances, documents internes, journal, communication interne.
  - **Client sans transitaire** (il gère lui-même, va directement à l'armateur) : voit **tout son dossier** et dispose de **plus d'options** qu'un client simple — position « intermédiaire » entre client et transitaire, sans toutefois les fonctionnalités propres au transitaire.
- Impact conception : **projections multiples** du détail dossier + policies selon la posture ; annuaire public des transitaires ; mécanisme de partage/ACL inter-tenant ; acceptation d'assignation.
- **Onboarding d'un client sans compte** (Lot 7) : au partage d'un dossier, le transitaire pré-crée un **compte client en attente** lié au dossier et envoie un **lien d'invitation signé à expiration** (e-mail ou **WhatsApp**). À l'ouverture, le client **réclame** le compte rapidement : **OTP** (idéalement **WhatsApp**, canal d'arrivée) + mot de passe ou **passkey** ; il accède aussitôt à sa vue (BL + parcours). Options de rapidité : magic link / passwordless, OTP WhatsApp natif, QR code (partage en personne / desktop→mobile), passkey dès la création ; repli : le transitaire rappelle / renvoie le lien.

## Questions ouvertes

- Table de correspondance précise `container_status` → statut Travess (à établir sur données réelles).
- Hébergement régional vs. global (latence Afrique de l'Ouest).
- Portail client : intégré au bundle web ou app séparée (par défaut intégré ; à trancher selon la charge).

## Journal

- **2026-09-10** — **Lot 5 complet** (Ingestion documentaire par IA) : contrat `ExtracteurDocument` avec adaptateurs factice (défaut, sans clé) et Claude via `laravel/ai`, DTO neutres et schémas paramétrables, pipeline en file (`LancerExtraction` → `ExtraireDocument` → `ValiderExtraction`) avec réservation atomique du quota et application au dossier sous validation humaine, décompte de consommation tenant-scopé, endpoints d'extraction/validation. Testable de bout en bout sans clé API. 136 tests PHP verts, Pint + Larastan niveau 5 à 0. Audit sécurité, revue et testeur passés. ADR-012.
- **2026-09-08** — Cadrage produit complet, choix de stack figés, documentation initiale rédigée, arborescence monorepo posée.
- **2026-09-10** — **Lot 2 complet** (Surestaries & alertes) : calcul des franchises en parité PHP↔TS, porteur de contexte tenant en file, moteur d'alertes J-3/J-1/J0 (scheduler), répartition multi-canal (email + stubs), tableaux de bord « argent en feu » / « surestaries évitées ». 109 tests PHP + parité TS. ADR-010/011. Audit/revue en cours.
- **2026-09-10** — **Lot 1 complet** (cœur dossier, surface agent) : dossiers (CRUD, liste filtrable, référence auto, workflow snapshot, clôture, assignation, audit), étapes, conteneurs/BL (ISO 6346 serveur), documents. Durcissement append-only de l'audit (trigger + RESTRICT, ADR-009). Audits sécurité + revue passés, correctifs intégrés. 89 tests PHP + 33 TS verts. ADR-006 à ADR-009.
- **2026-09-09** — Lot 0 quasi complet. Socle multi-tenant fail-closed + RLS PostgreSQL, auth Sanctum + 2FA Fortify (deux audits sécurité passés, durcissements appliqués), schéma complet (22 tables métier, dont 20 scopées par RLS + FK composites), ISO 6346 en parité PHP↔TS, packages `shared-core`/`shared-types`/`ui`, CI + Pint + Larastan. **85 tests verts** (56 PHP + 29 TS). ADR-002 à ADR-005. Reste : composants du design system (reportés au Lot 1). Dépôt distant : github.com/caurilab/travess.
