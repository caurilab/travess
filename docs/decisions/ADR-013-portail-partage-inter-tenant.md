# ADR-013 — Portail client : comptes client, partage inter-tenant borné (amende ADR-004)

## Statut
Accepté.

## Contexte
Le Lot 7 (portail client & onboarding) introduit deux réalités qu'ADR-004 excluait :
- des **comptes qui ne sont pas dans le tenant du transitaire** (le client) ;
- un **accès inter-tenant** à un dossier, pour qu'un client voie un dossier détenu par le tenant d'un transitaire.

Ces deux besoins doivent se concilier avec le **principe n°3** (le `tenant_id` vient du token, jamais du client) et avec le **fail-closed** posé par ADR-004 (en l'absence de contexte, on refuse plutôt que d'exposer). L'enjeu est d'ouvrir une visibilité inter-tenant **explicite, nominative et bornée** sans rouvrir l'angle mort que la défense en profondeur d'ADR-004 ferme.

## Décision

1. **Le compte client est son propre tenant `type=client`** — un workspace léger, sans quota IA ni tracking. L'équivalence `User.role=client` ⟺ tenant `type=client` est stricte ; le non-cumul (un compte = une posture) est garanti par l'**email unique au niveau plateforme** (déjà posé par ADR-004). Un compte est soit transitaire, soit client, jamais les deux.

2. **Un dossier a toujours un unique tenant propriétaire.** Ses enfants (BL, conteneurs, finances, documents, étapes) restent sous la RLS de ce tenant propriétaire, exactement comme aujourd'hui. Aucun enfant n'échappe au scoping du dossier.

3. **La visibilité inter-tenant passe par une table de partage explicite `acces_dossier`** :
   - **nominative** (un bénéficiaire précis, jamais un partage large) ;
   - **en LECTURE SEULE** ;
   - **projection en liste blanche** (les champs exposés sont énumérés positivement, jamais masqués a posteriori) ;
   - **prouvée en base** par une politique RLS `FOR SELECT` *grant-aware* s'appuyant sur un GUC `app.portail_user_id`, posé par le middleware portail et **jamais par le client**.

   L'invariant « aucun accès inter-tenant implicite » tient : hors d'une ligne `acces_dossier` correspondant au bénéficiaire courant, rien n'est visible.

4. **Trois niveaux d'accès** :
   - `limite` — client avec transitaire : **BL + parcours uniquement** ;
   - `etendu` — client autonome, agissant dans son propre tenant ;
   - `gestion` — transitaire propriétaire, vue complète.

5. **Le dossier porte une posture** `autonome` / `gere_par_transitaire`. Le passage `autonome → gere_par_transitaire` implique une **migration de propriété** (re-tenant de l'agrégat) **transactionnelle et auditée**. **Décision produit actée** : à cette bascule, le client passe en **vue limitée STRICTE** (BL + parcours) ; il **perd** l'accès aux finances et documents qu'il gérait jusque-là.

6. **`acces_dossier` est une exception documentée** aux FK composites `(id, tenant_id)` d'ADR-004 : par nature elle **relie deux tenants** (le propriétaire et le bénéficiaire) et **n'est pas auto-scopée** par `TenantScope`. Elle est **interrogée par bénéficiaire**, pas par tenant propriétaire.

7. **L'annuaire plateforme des transitaires est sur OPT-IN** du transitaire (décision produit actée) : un transitaire n'apparaît dans l'annuaire public que s'il l'a explicitement demandé.

## Ce qui reste d'ADR-004 (inchangé)
- Le `tenant_id` vient du token, jamais du client.
- Fail-closed : sans contexte, on refuse.
- `FORCE ROW LEVEL SECURITY` sur les tables scopées.
- FK composites `(id, tenant_id)` pour toutes les données tenant-internes.
- `TenantScope` + middleware de contexte.
- `runBypassed` audité pour les opérations transverses système.
- `User` et `Tenant` non auto-scopés (amorçage de l'authentification).

## Alternatives écartées
- **Comptes client sans tenant** : casse le fail-closed et l'obligation `dossier.tenant_id`. Rejeté — le client est un tenant `type=client`.
- **Masquage par liste noire des champs** : fragile (un nouveau champ est exposé par défaut). Rejeté au profit d'une **projection en liste blanche**.
- **Dossier détenu par le client avec finances du transitaire hors de son tenant** : brise l'unicité du tenant propriétaire et l'invariant « les enfants suivent le dossier ». Rejeté.
- **Deux dossiers distincts pour un même envoi** (un côté client, un côté transitaire) : duplication, divergence de données, réconciliation permanente. Rejeté au profit d'un dossier unique + partage.

## Conséquences
- Nouvelle **suite d'étanchéité du partage** à garder verte en permanence (aucun accès inter-tenant hors `acces_dossier`, projection bornée, GUC posé par le middleware seul).
- **7.0 (fondations du modèle d'accès)** doit être **audité avant 7.1+** : le socle d'isolation du portail est le point le plus sensible du lot.
- `runBypassed` **proscrit en bloc sur le chemin portail** : l'accès inter-tenant passe par la politique RLS *grant-aware*, pas par une échappatoire système ; un repli éventuel reste **borné à une liste explicite de `dossier_id`**, jamais un bypass large.
- **Mobile Money découplé** en sous-lot terminal **7.5** (paiement isolé du socle d'accès).

## Amendement 7.3b — Assignation & migration de propriété (2026-09-10)

Le sous-lot 7.3b (demande d'assignation client → transitaire, décision du transitaire, migration de propriété du dossier) précise et complète la décision sans la contredire.

### Table inter-tenant `demande_assignation` — SECONDE exception documentée aux FK composites d'ADR-004
Comme `acces_dossier` (point 6), la table `demande_assignation` **relie deux tenants** (le demandeur client et la cible transitaire) et constitue donc la **seconde exception documentée** aux FK composites `(id, tenant_id)` d'ADR-004 :
- **pas de `BelongsToTenant`** ni d'auto-scoping par `TenantScope` ;
- **RLS dédiée** : lecture ouverte au demandeur et à la cible ; **insertion réservée au demandeur** pour un dossier qu'il **possède ET qui est autonome** ; **décision ouverte aux deux parties** (dans leurs rôles respectifs) ;
- **unicité partielle** : au plus **une demande pendante par dossier** (index unique partiel sur l'état pendant).

### FK composites de l'agrégat dossier passées en `DEFERRABLE INITIALLY IMMEDIATE`
Les FK composites `(id, tenant_id)` de l'agrégat dossier deviennent `DEFERRABLE INITIALLY IMMEDIATE` : **comportement inchangé hors migration** (vérification immédiate, comme aujourd'hui). Seule la transaction de re-tenant émet `SET CONSTRAINTS ALL DEFERRED` le temps de réécrire le `tenant_id` de l'agrégat, puis rétablit `IMMEDIATE` pour **valider la cohérence dans la même transaction** (aucune fenêtre d'incohérence visible hors transaction).

### Migration de propriété (`MigrerProprieteDossier`)
La bascule `autonome → gere_par_transitaire` (point 5) est réalisée par une Action transactionnelle et auditée :
- **re-tenant de l'agrégat** dossier sous verrou, avec un **bypass borné au seul dossier** concerné (voir dette m2) ;
- **remap de l'armateur** vers le tenant du transitaire ;
- **régénération de la référence** du dossier (l'unicité de la référence est par tenant) ;
- **fiche client créée chez le transitaire** ;
- **octroi d'un `acces_dossier` de niveau `limite` actif** au client (BL + parcours) ;
- **posture → `gere_par_transitaire`** ;
- **audit bilatéral** (côté client cédant et côté transitaire repreneur).

**Invariant anti-orphelin** : aucun enfant de l'agrégat ne doit rester dans l'ancien tenant. Il est **garanti par un test de complétude piloté par le schéma** (la liste des tables enfant à re-tenanter est dérivée du schéma, non figée à la main). Les entités **`dossier_user`, `paiements` et `invitation_portail` sont « gardées »** : leur présence attachée au dossier **fait refuser la migration** (elles ne sont pas migrées silencieusement).

### Décision d'autorisation
**Décider une assignation est réservé à la cible** (le transitaire destinataire de la demande), **vérifié explicitement** dans l'autorisation — un tiers ou le demandeur ne peut pas décider à sa place.

## Date
2026-09-10
