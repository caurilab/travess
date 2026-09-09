# ADR-009 — Immutabilité du journal d'audit (append-only)

## Contexte
`audit_log` doit être inaltérable (docs/03 §8.2). Deux mécaniques ont été évaluées, à la lumière d'un fait déterminant : le rôle applicatif `travess` est **propriétaire** de la table.
- **REVOKE UPDATE/DELETE au rôle applicatif** : bloque les mutations accidentelles, mais le propriétaire peut se re-`GRANT` les privilèges — c'est un ralentisseur, pas un mur. Ne couvre pas `TRUNCATE` sans mention explicite.
- **Trigger BEFORE UPDATE/DELETE/TRUNCATE → exception** : indépendant de l'ownership, auto-documenté dans le schéma.

Par ailleurs, la FK `audit_log.tenant_id ON DELETE CASCADE` faisait de la destruction de l'audit un **effet de bord** de la suppression d'un tenant — inacceptable pour un journal de conformité.

## Décision
Durcissement au Lot 1 (migration `2026_09_10_100004_durcir_audit_log_append_only`) :
1. **Trigger append-only** (`audit_log_no_update_delete` sur UPDATE/DELETE, `audit_log_no_truncate` sur TRUNCATE) levant une exception `insufficient_privilege`. Couvre le modèle de menace « erreur de développement » quel que soit le rôle, sans changement d'infrastructure.
2. **FK `tenant_id ON DELETE RESTRICT`** : l'audit ne peut être purgé par effet de bord d'une suppression de tenant. **L'audit survit au tenant** ; sa purge éventuelle sera un acte délibéré, privilégié et tracé.

## Alternatives écartées
- REVOKE seul : re-`GRANT`-able par le propriétaire. Insuffisant tant que le rôle runtime possède la table.
- `ON DELETE CASCADE` (existant) : destruction silencieuse de la preuve. Rejeté.
- `ON DELETE SET NULL` : `tenant_id` est NOT NULL et sert de clé de scoping RLS ; rendrait les lignes inattribuables et invisibles. Rejeté.

## Conséquences
- Tests non impactés : `RefreshDatabase` = `migrate:fresh` (DROP … CASCADE, pas DELETE) + transactions rollback (pas des DELETE). Ne pas introduire de `TRUNCATE`/`DELETE` sur `audit_log` ni le trait `DatabaseTruncation`.
- **Reporté (lot « cycle de vie tenant » / infra)** : le vrai « mur » — un rôle runtime PostgreSQL **non-propriétaire** de `audit_log`, doté de `INSERT`/`SELECT` seulement — que l'application ne pourra pas lever ; et la conception de l'offboarding (purge sous politique de rétention).

## Date
2026-09-10
