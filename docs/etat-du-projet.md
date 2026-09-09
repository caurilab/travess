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

## À faire — prochaines actions

1. Confirmer auprès de JSONCargo : **HTTPS** de la base URL, et statut **Grimaldi**.
2. Choisir l'**agrégateur Mobile Money** et cadrer le volet réglementaire avec un conseil juridique local.
3. Lancer le **Lot 0** (fondations monorepo + API + auth + multi-tenant + modèle de données + design system).
4. Souscrire **JSONCargo Navigator** et instrumenter la consommation dès le Lot 4.

## Questions ouvertes

- Table de correspondance précise `container_status` → statut Travess (à établir sur données réelles).
- Hébergement régional vs. global (latence Afrique de l'Ouest).
- Portail client : intégré au bundle web ou app séparée (par défaut intégré ; à trancher selon la charge).

## Journal

- **2026-09-08** — Cadrage produit complet, choix de stack figés, documentation initiale rédigée, arborescence monorepo posée.
- **2026-09-09** — Lot 0 quasi complet. Socle multi-tenant fail-closed + RLS PostgreSQL, auth Sanctum + 2FA Fortify (deux audits sécurité passés, durcissements appliqués), schéma complet (22 tables métier, dont 20 scopées par RLS + FK composites), ISO 6346 en parité PHP↔TS, packages `shared-core`/`shared-types`/`ui`, CI + Pint + Larastan. **85 tests verts** (56 PHP + 29 TS). ADR-002 à ADR-005. Reste : composants du design system (reportés au Lot 1). Dépôt distant : github.com/caurilab/travess.
