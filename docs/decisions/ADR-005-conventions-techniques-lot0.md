# ADR-005 — Conventions techniques du Lot 0

## Contexte
Plusieurs choix techniques transverses devaient être tranchés au démarrage : clés primaires, représentation des enums en base, outillage des packages TypeScript, moteur des tests PHP, périmètre du design system.

## Décision
- **Clés primaires** : UUID **v7** (ordonnées dans le temps → bonne localité d'index). Générées par le trait `HasUuids` de Laravel 13 (`Str::uuid7()`), porté par `App\Shared\Models\BaseModel`.
- **Enums en base** : `varchar` + contrainte `CHECK` (valeurs issues d'enums PHP), et non des types `ENUM` PostgreSQL (plus simples à faire évoluer). Les enums PHP sont la source des valeurs, reflétées dans `packages/shared-types`.
- **Colonnes JSON** : `jsonb` (pas `json`). Secrets (`imap_config`, 2FA) chiffrés au repos.
- **Outillage packages TS** : `tsup` (build ESM + CJS + types) et `vitest` (tests), `tsconfig.base.json` strict à la racine `packages/`. Pas de Vite en mode bibliothèque au Lot 0.
- **Tests PHP sur PostgreSQL** : `phpunit.xml` cible une base `travess_test` PostgreSQL (et non SQLite `:memory:`), pour la parité moteur (jsonb, CHECK, UUID, RLS).
- **Design system (`packages/ui`)** : au Lot 0, uniquement les **jetons** (couleurs, espacements, rayons, ombres, typographie) et le **jeu de statuts normalisé**. Direction validée : marque corail + rouge d'urgence distinct (à risque), ambre (en cours), vert (réglé). Les composants de base sont reportés au Lot 1 pour être développés contre `travess-web` (consommateur réel).
- **Auth** : Sanctum (jetons Bearer, uniformes web/desktop/mobile) + Fortify (2FA TOTP + codes de récupération, API-only). Passkeys/WebAuthn = incrément dédié ultérieur (ossature seulement au Lot 0).

## Alternatives écartées
- **UUID v4** (`gen_random_uuid`) : fragmentation d'index sur les insertions ordonnées. Écarté au profit de v7.
- **Types `ENUM` PostgreSQL** : évolution pénible (ALTER TYPE). Écarté.
- **Tests sur SQLite** : moteur différent de la prod, incompatible avec jsonb/CHECK/RLS. Écarté.
- **Composants `ui` dès le Lot 0** : spéculatifs sans consommateur. Reportés au Lot 1.

## Conséquences
- La base de développement et de test nécessite PostgreSQL (fourni par `docker-compose`, ou localement).
- Chaque table parente scopée applique le patron `UNIQUE(id, tenant_id)` + RLS (cf. ADR-004) via le helper `App\Shared\Database\RlsTenant`.

## Date
2026-09-09
