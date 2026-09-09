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

## Avancement — Lot 7 (Portail & onboarding)

> En cours. Modèle d'accès conçu et validé par arbitrage produit ; décision consignée en ADR-013 (amende ADR-004). Le socle d'isolation du portail est le point le plus sensible du lot.

**Modèle retenu :**
- **Le compte client est son propre tenant `type=client`** (workspace léger, sans quota IA ni tracking). Équivalence stricte `User.role=client` ⟺ tenant `type=client` ; non-cumul garanti par l'email unique plateforme (un compte = une posture).
- **Un dossier a toujours un unique tenant propriétaire** ; ses enfants (BL, conteneurs, finances, documents, étapes) restent sous sa RLS.
- **Partage inter-tenant explicite via `acces_dossier`** : nominatif, lecture seule, projection en liste blanche, prouvé en base par une politique RLS `FOR SELECT` *grant-aware* s'appuyant sur le GUC `app.portail_user_id` (posé par le middleware portail, jamais par le client). L'invariant « aucun accès inter-tenant implicite » tient.
- **Trois niveaux d'accès** : `limite` (client avec transitaire : BL + parcours), `etendu` (client autonome, dans son propre tenant), `gestion` (transitaire propriétaire).
- **Posture du dossier** `autonome` / `gere_par_transitaire` ; la bascule `autonome → gere_par_transitaire` implique une migration de propriété (re-tenant de l'agrégat) transactionnelle et auditée.
- **Onboarding** : lien d'invitation **signé à expiration** + réclamation du compte par **OTP** (idéalement WhatsApp, canal d'arrivée) ; accès immédiat à la vue BL + parcours.

**Décisions produit actées :**
- **Bascule stricte** : au passage autonome → géré, le client passe en vue limitée STRICTE (BL + parcours) et perd l'accès aux finances/documents qu'il gérait.
- **Annuaire opt-in** : un transitaire n'apparaît dans l'annuaire public que s'il l'a explicitement demandé.
- **Mobile Money découplé** en sous-lot terminal 7.5 (paiement isolé du socle d'accès).

**Découpage 7.0 → 7.5 :**
- **7.0 — Fondations du modèle d'accès** (compte client = tenant `type=client`, table `acces_dossier` + RLS *grant-aware*, GUC `app.portail_user_id`, trois niveaux, posture du dossier). **Premier incrément, à auditer avant la suite.**
- **7.1+** — projections bornées du dossier, flux d'assignation/invitation, onboarding (lien signé + OTP), annuaire opt-in, bascule de posture auditée.
- **7.5 — Mobile Money** (sous-lot terminal).

## Avancement — Lot 7.2a (Onboarding portail)

> Terminé et audité. Met en œuvre ADR-013 côté onboarding (aucun ADR nouveau : le cadre reste ADR-013). Adaptateurs factices par défaut : le flux est exerçable de bout en bout sans fournisseur réel. Audit sécurité passé (GO conditionnel, 3 majeurs corrigés).

**Flux couvert :** émission (transitaire) → réclamation publique (OTP) → confirmation (provisionnement du compte client + activation de l'accès).
- **Émission** : le transitaire émet une invitation liée au dossier partagé ; lien signé à expiration.
- **Réclamation publique** : le destinataire ouvre le lien et valide par **OTP** (canal d'arrivée).
- **Confirmation** : à la validation, provisionnement du compte client (tenant `type=client`) et activation de l'accès `acces_dossier`.

**Domaine Messagerie (principe n°9) :**
- Contrats isolés `ExpediteurMessage` et `ServiceOtp`, adaptateurs factices `ExpediteurFactice` / `ServiceOtpFactice` (déterministes, sans réseau).
- `config/messagerie.php` : **driver factice par défaut** ; le fournisseur réel est branché derrière les interfaces sans toucher au métier.

**Décisions produit actées :**
- **Identité du compte client = téléphone (E.164) + OTP** ; email facultatif.
- **Non-cumul strict** : un numéro déjà associé à un compte non-client entraîne un **refus 409** (jamais de fusion ni de bascule de posture).

**Sécurité :**
- Token d'invitation **256 bits, haché au repos** (jamais stocké en clair), transporté par **lien signé**.
- **RLS `invitation_portail` bornée au token** via le GUC `app.invitation_token_hash` (posé côté serveur, jamais par le client).
- **OTP à usage unique** lié à l'invitation : tentatives persistées, verrou, plafonds d'émission.
- **Provisionnement sous `runBypassed` borné et audité** (opération transverse système, tracée).
- **Charge du job chiffrée**.
- **Reset fail-closed des GUC** sur le chemin public (aucun contexte résiduel ne fuit d'une requête à l'autre).

**Vérification :** 160 tests verts, Pint + Larastan à 0. Audit sécurité passé (GO conditionnel : 3 majeurs corrigés).

## Dette technique / à sécuriser avant prod (Lot 7 — onboarding & providers)

- **Providers réels à brancher derrière les interfaces (7.2b+)**, tant qu'ils ne le sont pas les canaux `whatsapp`/`sms` restent en **factice/différé** :
  - **Email transactionnel réel** : SPF/DKIM/DMARC sur `travess.ci`.
  - **WhatsApp Business API** : numéro vérifié, templates validés, modèle de coût, rétention Meta.
  - **Fournisseur SMS/OTP** : sender ID compatible BCEAO, protection anti SMS-pumping, plafond de dépense.
- **Note d'audit m1 (ops)** : le token voyage dans le **chemin de l'URL signée** — garantir côté exploitation que ce chemin **n'est pas journalisé** (logs proxy/serveur) et **pas de fuite via `Referer`**. Risque mitigé par OTP + usage unique + TTL 72 h.
- **Note d'audit m4 (UX)** : l'OTP est **consommé avant la fin du provisionnement** ; un échec tardif (ex. refus 409 de non-cumul) oblige à **re-réclamer**.
- **Point de contrôle sécurité à rouvrir avant / à 7.2b** (branchement des providers réels) : ré-auditer le chemin d'onboarding avec les fournisseurs réels en place.

## Avancement — Lot 7.3b (Assignation & migration de propriété)

> Terminé et audité. Met en œuvre ADR-013 (amendement 7.3b, aucun ADR nouveau). Audit sécurité passé après correctifs (NO-GO initial levé).

**Fait et vérifié :**
- **Demande d'assignation** client → transitaire via la table inter-tenant `demande_assignation` (seconde exception documentée aux FK composites d'ADR-004, comme `acces_dossier`) : pas de `BelongsToTenant`, **RLS dédiée** (lecture demandeur/cible, insertion réservée au demandeur pour un dossier possédé **et** autonome, décision aux deux parties), **unicité partielle** « une demande pendante par dossier ».
- **Décision de l'assignation réservée à la cible** (le transitaire), vérifiée explicitement dans l'autorisation.
- **FK composites de l'agrégat dossier en `DEFERRABLE INITIALLY IMMEDIATE`** : comportement inchangé hors migration ; `SET CONSTRAINTS ALL DEFERRED` pendant le re-tenant puis retour `IMMEDIATE` pour valider dans la même transaction.
- **Migration de propriété** (`MigrerProprieteDossier`, transactionnelle et auditée) : re-tenant de l'agrégat sous verrou avec bypass **borné au seul dossier**, remap de l'armateur vers le tenant transitaire, régénération de la référence (unicité par tenant), fiche client créée chez le transitaire, octroi d'un `acces_dossier` niveau `limite` actif au client, posture → `gere_par_transitaire`, **audit bilatéral**.
- **Invariant anti-orphelin** garanti par un **test de complétude piloté par le schéma** ; `dossier_user`, `paiements` et `invitation_portail` sont « gardés » — leur présence attachée fait **refuser** la migration.
- **175 tests verts**, Pint + Larastan à 0. **Audit sécurité passé** (NO-GO initial sur la complétude de l'agrégat — B1 — et la garde de la cible — M1 —, tous deux corrigés).

## Dette technique identifiée (Lot 7.3b — assignation & migration)

- **m2** — `runBypassed` **lève la RLS globalement** pendant la fenêtre de re-tenant ; il est **borné par un prédicat mono-dossier** (un seul `dossier_id`). Écart assumé vs la « liste explicite de `dossier_id` » posée en conséquence d'ADR-013.
- **m3** — les **notifications restent chez le client** après la bascule (pas de re-tenant du journal de notifications) : **pas de fuite financière**, journal cloisonné par tenant. À réévaluer si besoin ultérieur.

## Questions ouvertes

- Table de correspondance précise `container_status` → statut Travess (à établir sur données réelles).
- Hébergement régional vs. global (latence Afrique de l'Ouest).
- Portail client : intégré au bundle web ou app séparée (par défaut intégré ; à trancher selon la charge).

## Journal

- **2026-09-10** — **Lot 7.2b complet** (Expéditeur e-mail réel) : `ExpediteurEmail` (mailable `InvitationMail`) branché derrière `ExpediteurMessage` en driver `reel` (MESSAGERIE_DRIVER=reel), envoi SYNCHRONE depuis le job `EnvoyerInvitation` (le lien secret ne transite pas par la file). WhatsApp/SMS restent différés (`ExpediteurDiffere`) en attendant les fournisseurs agréés (7.2c). 180 tests verts, Pint + Larastan 0. Dépendance prod : configurer MAIL_* + SPF/DKIM/DMARC sur travess.ci.
- **2026-09-10** — **Lot 7.4 complet** (Annuaire opt-in des transitaires) : un transitaire (gérant) active sa visibilité dans l'annuaire de la plateforme (`tenants.annuaire_public`, Action auditée) ; un client autonome ne voit et n'assigne que les transitaires opt-in (`GET /portail/autonome/annuaire`, `DemanderAssignation` refuse un transitaire hors annuaire → 422). Surfaces et Resources dédiées (liste blanche id/nom). 179 tests verts, Pint + Larastan 0. Met en œuvre ADR-013 (décision produit : annuaire opt-in). Restent 7.2b (email réel) et 7.5 (Mobile Money).
- **2026-09-10** — **Lot 7.3b complet** (Assignation & migration de propriété) : demande d'assignation client → transitaire via la table inter-tenant `demande_assignation` (seconde exception documentée aux FK composites d'ADR-004, comme `acces_dossier` — pas de `BelongsToTenant`, RLS dédiée lecture demandeur/cible + insertion réservée au demandeur pour un dossier possédé et autonome + décision aux deux parties, unicité partielle « une demande pendante par dossier »), décision réservée à la cible transitaire (vérifiée explicitement). FK composites de l'agrégat dossier passées en `DEFERRABLE INITIALLY IMMEDIATE` (inchangé hors migration ; `SET CONSTRAINTS ALL DEFERRED` pendant le re-tenant puis `IMMEDIATE`). Migration de propriété (`MigrerProprieteDossier`) transactionnelle et auditée : re-tenant de l'agrégat sous verrou + bypass borné au seul dossier, remap armateur vers le tenant transitaire, régénération de la référence (unicité par tenant), fiche client créée chez le transitaire, octroi `acces_dossier` niveau `limite` actif au client, posture → `gere_par_transitaire`, audit bilatéral ; invariant anti-orphelin garanti par un test de complétude piloté par le schéma, `dossier_user`/`paiements`/`invitation_portail` « gardés » (migration refusée si attachés). 175 tests verts, Pint + Larastan 0. Audit sécurité passé (NO-GO initial sur la complétude de l'agrégat B1 et la garde de la cible M1, tous deux corrigés). Met en œuvre ADR-013 (amendement 7.3b). Dette : m2 (`runBypassed` lève la RLS globalement pendant la fenêtre de re-tenant, borné par prédicat mono-dossier — écart assumé vs « liste explicite de `dossier_id` »), m3 (notifications restent chez le client après bascule — pas de fuite financière, journal par tenant). Restent 7.2b (email transactionnel réel), 7.4 (annuaire opt-in) et 7.5 (Mobile Money).
- **2026-09-10** — **Lot 7.3a complet** (Client autonome & vue étendue) : un compte client crée et gère SES dossiers (dossier + BL + conteneurs + parcours) dans son propre workspace, sur une surface dédiée `/portail/autonome/*` — jamais la surface agent. Colonne `dossiers.posture` (`autonome`/`gere_par_transitaire`, existants backfillés en `gere_par_transitaire`), fiche client « self » auto-provisionnée (`clients.est_self`), armateur provisionné par nom faute d'annuaire côté client. Middleware `EnsureAutonomeContext` (rôle client + tenant `type=client`), `DossierAutonomePolicy` posture-aware, Resources en liste blanche. Le dossier autonome vit dans le tenant du client (RLS normale, aucun `acces_dossier`). Périmètre MVP restreint (décision produit). 165 tests verts, Pint + Larastan 0. Met en œuvre ADR-013. Reste 7.3b : demande d'assignation + acceptation transitaire + migration de propriété (re-tenant de l'agrégat, FK DEFERRABLE) — moteur de migration à pré-auditer.
- **2026-09-10** — **Lot 7.2a complet** (Onboarding portail) : flux émission (transitaire) → réclamation publique (OTP) → confirmation (provisionnement du compte client `type=client` + activation de l'accès). Domaine Messagerie isolé derrière `ExpediteurMessage`/`ServiceOtp` (principe n°9), adaptateurs factices par défaut (`config/messagerie.php`) rendant le flux exerçable de bout en bout sans fournisseur réel. Décisions produit actées : identité du compte client = téléphone (E.164) + OTP (email facultatif), non-cumul strict (numéro déjà pris par un compte non-client → 409). Sécurité : token d'invitation 256 bits haché au repos, lien signé, RLS `invitation_portail` bornée au token (GUC `app.invitation_token_hash`), OTP à usage unique avec tentatives/verrou/plafonds, provisionnement sous `runBypassed` borné et audité, charge de job chiffrée, reset fail-closed des GUC sur le chemin public. 160 tests verts, Pint + Larastan 0. Audit sécurité passé (GO conditionnel : 3 majeurs corrigés). Met en œuvre ADR-013 (pas de nouvel ADR). Dette consignée : providers réels (email/WhatsApp/SMS-OTP) à brancher en 7.2b+, notes d'audit m1 (token dans l'URL — non-journalisation côté ops) et m4 (OTP consommé avant fin de provisionnement), point de contrôle sécurité à rouvrir à 7.2b.
- **2026-09-10** — **Lot 7 — modèle d'accès du portail conçu et validé** (arbitrage produit) : compte client = tenant `type=client` ; partage inter-tenant borné en lecture via `acces_dossier` + politique RLS `FOR SELECT` *grant-aware* (GUC `app.portail_user_id`) ; trois niveaux d'accès (`limite`/`etendu`/`gestion`) ; posture du dossier `autonome`/`gere_par_transitaire` avec migration de propriété auditée. Trois décisions produit actées : bascule stricte BL+parcours à la reprise par un transitaire, annuaire des transitaires en opt-in, Mobile Money découplé en sous-lot 7.5. Découpage 7.0 → 7.5, 7.0 (fondations du modèle d'accès) à auditer avant la suite. ADR-013 (amende ADR-004).
- **2026-09-10** — **Lot 5 complet** (Ingestion documentaire par IA) : contrat `ExtracteurDocument` avec adaptateurs factice (défaut, sans clé) et Claude via `laravel/ai`, DTO neutres et schémas paramétrables, pipeline en file (`LancerExtraction` → `ExtraireDocument` → `ValiderExtraction`) avec réservation atomique du quota et application au dossier sous validation humaine, décompte de consommation tenant-scopé, endpoints d'extraction/validation. Testable de bout en bout sans clé API. 136 tests PHP verts, Pint + Larastan niveau 5 à 0. Audit sécurité, revue et testeur passés. ADR-012.
- **2026-09-08** — Cadrage produit complet, choix de stack figés, documentation initiale rédigée, arborescence monorepo posée.
- **2026-09-10** — **Lot 2 complet** (Surestaries & alertes) : calcul des franchises en parité PHP↔TS, porteur de contexte tenant en file, moteur d'alertes J-3/J-1/J0 (scheduler), répartition multi-canal (email + stubs), tableaux de bord « argent en feu » / « surestaries évitées ». 109 tests PHP + parité TS. ADR-010/011. Audit/revue en cours.
- **2026-09-10** — **Lot 1 complet** (cœur dossier, surface agent) : dossiers (CRUD, liste filtrable, référence auto, workflow snapshot, clôture, assignation, audit), étapes, conteneurs/BL (ISO 6346 serveur), documents. Durcissement append-only de l'audit (trigger + RESTRICT, ADR-009). Audits sécurité + revue passés, correctifs intégrés. 89 tests PHP + 33 TS verts. ADR-006 à ADR-009.
- **2026-09-09** — Lot 0 quasi complet. Socle multi-tenant fail-closed + RLS PostgreSQL, auth Sanctum + 2FA Fortify (deux audits sécurité passés, durcissements appliqués), schéma complet (22 tables métier, dont 20 scopées par RLS + FK composites), ISO 6346 en parité PHP↔TS, packages `shared-core`/`shared-types`/`ui`, CI + Pint + Larastan. **85 tests verts** (56 PHP + 29 TS). ADR-002 à ADR-005. Reste : composants du design system (reportés au Lot 1). Dépôt distant : github.com/caurilab/travess.
