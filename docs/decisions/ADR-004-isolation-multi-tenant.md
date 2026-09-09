# ADR-004 — Isolation multi-tenant (défense en profondeur)

## Contexte
Travess est multi-tenant sur base unique. L'isolation par `tenant_id` est la faille la plus grave à éviter (`docs/03` §8.1, principe n°3). Le `tenant_id` ne doit jamais venir du client, et aucun accès inter-tenant ne doit être possible au niveau applicatif.

## Décision
Défense en profondeur à trois couches, **fail-closed** (en l'absence de tenant, on refuse plutôt que d'exposer) :

1. **Couche modèle (Eloquent)** — trait `BelongsToTenant` sur tout modèle métier :
   - global scope `TenantScope` filtrant `tenant_id = <tenant courant>` ; lève une exception si aucun contexte n'est établi ;
   - à la création, `tenant_id` est **forcé** depuis le contexte (un `tenant_id` fourni par le client est ignoré et écrasé).
2. **Couche transport (HTTP)** — middleware `EnsureTenantContext` : rejette (422) toute requête portant `tenant_id`/`tenant` en entrée (query, corps JSON, route), refuse (403) une session sans tenant, dépose le tenant issu de `user.tenant_id` dans `TenantContext`.
3. **Couche base (PostgreSQL Row-Level Security)** — chaque table scopée active `ENABLE`/`FORCE ROW LEVEL SECURITY` avec une politique comparant `tenant_id` au GUC de session `app.tenant_id` (posé par `TenantContext`). Ferme les angles morts d'Eloquent (`insert`, `upsert`, `DB::table`) et un contexte manquant. Une échappatoire système explicite et auditée (`app.bypass_rls`) autorise les opérations transverses via `TenantContext::runBypassed()`.

Compléments :
- **`tenant_id` dénormalisé** sur toutes les tables filles (scoping uniforme sans jointure).
- **FK composites** : les tables parentes scopées portent `UNIQUE(id, tenant_id)`, cible des `FOREIGN KEY (parent_id, tenant_id)` des tables filles → un enfant ne peut structurellement pas pointer un parent d'un autre tenant.
- **`TenantContext` en binding `scoped`** (réinitialisé entre requêtes sous Octane) + `forget()` en fin de middleware.
- **Rôle applicatif** non-superuser et sans `BYPASSRLS`, propriétaire des tables (donc soumis à `FORCE RLS`).

### Exception assumée : `Tenant` et `User` non auto-scopés
`Tenant` est la racine (pas de `tenant_id`). `User` participe à l'amorçage de l'authentification : Sanctum résout l'utilisateur porteur du jeton **avant** que le middleware ne pose le contexte, et le login résout par email avant de connaître le tenant. `User` n'est donc pas auto-scopé (ni RLS) ; l'email est **unique au niveau plateforme**. Le confinement des utilisateurs par tenant se fait explicitement (scope `forTenant` + policy + route-model binding scopé + test dédié — à livrer avec l'auth, lot 0.c).

## Alternatives écartées
- **Scoping par jointure sans dénormaliser `tenant_id`** : l'étanchéité dépendrait de la chaîne de FK ; rejeté au profit de la colonne uniforme + RLS.
- **Isolation 100 % applicative (sans RLS)** : laisse ouverts `insert`/`upsert`/`DB::table` et un contexte manquant. Rejeté après audit sécurité.
- **`User` auto-scopé** : casse la résolution Sanctum/login sans contexte. Rejeté ; compensé par des garde-fous explicites.

## Conséquences
- Chaque requête et chaque job doivent établir le contexte tenant (le middleware le fait en HTTP ; un porteur pour les jobs est une dette identifiée avant le premier traitement en file).
- La suite d'étanchéité inter-tenant (`tests/Feature/Tenancy`) est un **invariant permanent** à garder vert à chaque lot ; un test anti-régression exige `BelongsToTenant` sur tout modèle portant `tenant_id`.
- Les tests tournent sur PostgreSQL (parité moteur) pour exercer réellement la RLS.

## Date
2026-09-09
