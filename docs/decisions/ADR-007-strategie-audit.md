# ADR-007 — Journal d'audit : service maison appelé dans les Actions

## Contexte
Toute mutation métier (création, mise à jour, changement de statut, assignation, clôture) doit être tracée dans `audit_log` (scopé + RLS) de façon systématique, non contournable et inaltérable (docs/03 §8.2, docs/07).

## Décision
- **Service maison** `App\Domains\Audit\Services\Auditeur`, appelé **explicitement depuis chaque Action de mutation**, dans la **même transaction** que la mutation (atomicité : les deux ou rien).
- **Non contournable par construction** : toute mutation d'état passe par une Action (cf. ADR-008), unique point de passage où l'audit est écrit.
- **Renseignement** : `tenant_id` forcé par `BelongsToTenant` depuis le contexte ; `user_id` depuis l'utilisateur authentifié (`Auth::id()`, null pour une écriture système/queue), jamais du client ; `avant`/`apres` construits par l'Action à partir des attributs d'origine et de `getChanges()` ; `action` sémantique (`dossier.creation`, `dossier.cloture`, `dossier.assignation`…).

## Alternatives écartées
- **spatie/laravel-activitylog** : schéma propre incompatible avec `audit_log` existant (tenant_id forcé + RLS + `at` + entite/entite_id/avant/apres). Dupliquer ou détourner la table irait contre « réutilise l'existant ».
- **Observers Eloquent / trait Auditable** : capturent la mutation technique mais perdent l'intention métier, risquent le double-log et gèrent mal une action touchant plusieurs lignes. Inadaptés comme mécanisme primaire.

## Conséquences
- Discipline à tenir : pas de mutation d'état hors Action auditée (revue + à terme un test-garde).
- **Inaltérabilité stricte** (`REVOKE UPDATE/DELETE ON audit_log` au rôle applicatif) reste un renforcement ultérieur, sous validation `auditeur-securite` ; le modèle est déjà append-only côté ORM (`timestamps = false`, pas d'endpoint d'écriture).

## Date
2026-09-10
