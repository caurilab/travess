# Travess — Architecture technique

---

## 1. Vue d'ensemble

Un monorepo unique héberge quatre applications qui partagent un cœur commun (types, logique métier, design system) et parlent toutes à une seule API.

```
                          ┌──────────────────┐
                          │   travess-api    │  Laravel 13 / PHP 8.3+
                          │  (REST + queues) │  PostgreSQL
                          └────────┬─────────┘
                                   │  HTTPS / JSON
          ┌────────────────┬───────┴────────┬──────────────────┐
          │                │                │                  │
   ┌──────┴──────┐  ┌──────┴──────┐  ┌──────┴──────┐   ┌────────┴───────┐
   │ travess-web │  │travess-desktop│ │travess-mobile│  │  portail client │
   │  React 19   │  │  Electron +   │ │ React Native │  │ (dans web ou    │
   │  (agent)    │  │  bundle web   │ │  (natif)     │  │  app dédiée)    │
   └─────────────┘  └───────────────┘ └──────────────┘  └─────────────────┘
```

Services externes : **JSONCargo** (tracking), **provider de vision IA** (via Laravel AI SDK), **agrégateur Mobile Money**, **WhatsApp Business API**, **serveurs IMAP** des armateurs.

## 2. Le monorepo

```
travess/
├── .claude/
│   ├── agents/            # sous-agents de développement (voir 10)
│   └── CLAUDE.md          # règles projet pour l'assistant de code
├── .github/              # CI/CD
├── docs/                 # toute la documentation produit & technique
│   └── decisions/        # ADR (Architecture Decision Records)
├── ops/                  # docker, scripts d'infra, déploiement
├── travess-api/          # backend Laravel 13
├── travess-web/          # front agent React 19 (+ portail client)
├── travess-mobile/       # app React Native
├── travess-desktop/      # shell Electron
├── packages/             # code partagé JS/TS
│   ├── shared-types/     # types TypeScript (miroir des DTO API)
│   ├── shared-core/      # logique métier portable (ISO 6346, calcul surestaries…)
│   └── ui/               # design system partagé web/desktop
├── .env.example
├── docker-compose.yml
├── Makefile
├── package.json          # racine workspaces (pnpm)
├── pnpm-workspace.yaml
├── turbo.json            # Turborepo
├── PROMPT-DEMARRAGE.md   # prompt de lancement pour l'agent de code
└── README.md
```

### 2.1 Outillage monorepo
- **pnpm workspaces** pour les paquets JS/TS (`travess-web`, `travess-mobile`, `travess-desktop`, `packages/*`).
- **Turborepo** pour l'orchestration des tâches (build, lint, test) avec cache.
- `travess-api` (Laravel/Composer) vit dans le même dépôt mais garde son propre cycle Composer ; il n'entre pas dans les workspaces pnpm.

### 2.2 Le partage de code — principe cardinal
La logique métier sensible est écrite **une seule fois** dans `packages/shared-core` et consommée par web, desktop et mobile :
- validation **ISO 6346** (chiffre de contrôle) ;
- calcul des **surestaries / détention** à partir d'un barème et de dates ;
- calcul de la **prochaine échéance de poll** d'un conteneur ;
- formats et helpers métier communs.

Les **types** (`packages/shared-types`) sont le miroir des DTO exposés par l'API. Toute évolution du contrat d'API se répercute ici, garantissant que les trois clients restent alignés.

## 3. Backend — travess-api

### 3.1 Stack
- **Laravel 13**, PHP 8.3+.
- **PostgreSQL** comme base principale (JSONB pour les réponses de tracking, PostGIS envisageable pour la géolocalisation).
- **Redis** pour le cache, les files et les verrous.
- **File d'attente** (queues Laravel) pour tout traitement lourd : extraction IA, appels tracking, envoi de notifications, génération PDF.
- **Ordonnanceur** (scheduler Laravel) pour le polling intelligent et les calculs d'alertes.
- **Laravel AI SDK** (stable depuis la 13) pour l'extraction documentaire, en mode provider-agnostic.

### 3.2 Découpage en modules (domaines)
L'API est organisée par domaine métier plutôt qu'en couches techniques :
- `Tenancy` — sociétés, plans, quotas, isolation.
- `Identity` — utilisateurs, rôles, permissions, auth.
- `Dossiers` — dossiers, étapes, workflow, audit.
- `Conteneurs` — conteneurs, ISO 6346, statuts.
- `Surestaries` — barèmes, calcul, alertes.
- `Finances` — charges, encaissements, honoraires, échéancier, PDF.
- `Transport` — camions, chauffeurs, géolocalisation, livraison.
- `Tracking` — intégration JSONCargo, scheduler, fallback IMAP.
- `Ingestion` — dépôt documents, queue, extraction IA, validation.
- `Portail` — espace client, accès restreint.
- `Paiements` — intégration Mobile Money, commission, reçus.
- `Messagerie` — IMAP, WhatsApp, notifications.

### 3.3 API
- REST, JSON, versionnée (`/api/v1/`).
- Authentification par jetons (Sanctum) — adaptés aux clients web, desktop et mobile.
- Toutes les réponses scoping tenant appliqué de façon systématique et non contournable.
- Documentation d'API générée (OpenAPI) et publiée dans `docs/`.

### 3.4 Isolation multi-tenant
- Colonne `tenant_id` sur chaque table métier.
- Scoping global appliqué au niveau du modèle (global scope) + garde-fou middleware.
- Le `tenant_id` provient du contexte d'authentification, jamais d'un paramètre client.

## 4. Front web — travess-web

- **React 19**, **React Compiler** activé (mémoïsation automatique).
- TypeScript strict, types importés de `packages/shared-types`.
- Design system depuis `packages/ui`.
- Routage, gestion d'état serveur (cache de requêtes), formulaires robustes.
- Deux cibles dans le même code : **espace agent** et **portail client** (routes et layouts distincts, permissions distinctes). Le portail peut être extrait en app séparée si la charge le justifie ; par défaut il partage la base.
- Build produisant un bundle statique consommable aussi par Electron.

## 5. Desktop — travess-desktop

### 5.1 Principe
Electron charge le **bundle React de `travess-web`** empaqueté localement (pas une URL distante, pas une réécriture). Le desktop = web + capacités natives.

### 5.2 Structure
- **Processus principal** (main) : fenêtre, cycle de vie, accès système.
- **Processus de rendu** (renderer) : le bundle React.
- **Preload** : pont sécurisé exposant au renderer des API natives contrôlées (contextIsolation activée, pas d'accès Node direct au renderer).

### 5.3 Capacités natives (la valeur du desktop)
- **Hors-ligne** : base **SQLite** locale, cache des dossiers actifs, file de mutations en attente, synchronisation à la reconnexion avec résolution de conflits (dernier écrivain / marquage manuel selon les cas).
- **Notifications système** natives pour les alertes surestaries.
- **Impression directe** documents / factures.
- **Scan** via périphérique USB.
- **Raccourcis clavier** globaux de saisie.

### 5.4 Empaquetage
- Electron Builder, cibles Windows en priorité (parc courant en agence), macOS ensuite.
- Mise à jour automatique.

## 6. Mobile — travess-mobile

- **React Native** (natif, distinct du web — usages différents).
- TypeScript, types partagés de `packages/shared-types`, logique de `packages/shared-core`.
- Modules natifs : appareil photo, scan (numéro conteneur / code-barres), géolocalisation, notifications push.
- Deux profils d'app dans un même binaire : **agent** et **chauffeur** (le second réduit à sa mission).
- Résilience réseau : files locales, envoi différé, reprise.

## 7. Intégrations externes

### 7.1 JSONCargo (tracking)
- Un abonnement mutualisé. Clé stockée côté API, jamais exposée aux clients.
- Domaine `Tracking` : appels, scheduler intelligent, journalisation par tenant, plafond de sécurité, fallback IMAP. Détail dans `08` et `09`.

### 7.2 Vision IA (ingestion)
- Via Laravel AI SDK (provider-agnostic : le provider peut changer sans réécrire le domaine).
- Traitement en queue, validation humaine obligatoire.

### 7.3 Mobile Money
- Via un agrégateur agréé (partenariat). Domaine `Paiements` : initiation, callback, réconciliation, commission, reçu. Détail dans `11`.

### 7.4 WhatsApp Business API
- Notifications sortantes (templates) et réception de médias entrants rattachés au dossier.

### 7.5 IMAP
- Fallback tracking et passerelle armateur. Rattachement des mails au dossier par heuristique (objet, n° BL).

## 8. Infrastructure & déploiement

- **Docker** pour le développement (docker-compose : api, postgres, redis, worker de queue, scheduler).
- CI/CD via `.github/` : lint, tests, build par application, déploiement.
- Environnements : local, staging, production.
- Secrets hors dépôt (`.env`, gestionnaire de secrets en production).
- Sauvegardes PostgreSQL régulières, stockage documents sur objet (S3-compatible).

## 9. Principes d'architecture (à ne pas transgresser)

1. **Le cœur métier s'écrit une fois** (`shared-core`) et se réutilise partout.
2. **Le contrat d'API est la source de vérité** ; les types clients en découlent.
3. **Le scoping tenant n'est jamais optionnel** ni contrôlé par le client.
4. **Tout traitement lourd passe en queue** ; l'agent n'attend jamais un appel externe.
5. **Le tracking respecte le plafond de sécurité** ; aucun polling aveugle.
6. **L'IA propose, l'humain valide** ; jamais d'écriture automatique.
7. **Un navire s'identifie par IMO/MMSI**, jamais par son nom.
8. **Le desktop réutilise le web** ; le mobile est natif et distinct.
