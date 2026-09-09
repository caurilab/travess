# Travess

> **Travess** — contraction de *transit* et *vessel*. SaaS multi-tenant pour les entreprises de transit et transitaires d'Afrique de l'Ouest.

Suivi de dossiers d'import/export, gestion des conteneurs et des surestaries, tracking navire/conteneur automatique, ingestion documentaire par IA, portail client avec paiement Mobile Money. Quatre surfaces : web agent, desktop (Electron), mobile (React Native), portail client.

## Documentation

Toute la documentation produit et technique est dans [`docs/`](docs/) :

| Doc | Contenu |
|---|---|
| `01-vision-et-concept.md` | Le problème, la proposition de valeur, les surfaces |
| `02-modele-economique.md` | Sources de revenu, coûts, principe directeur |
| `03-prd.md` | **PRD complet** — utilisateurs, fonctionnalités, lots |
| `04-architecture.md` | Monorepo, stack, principes techniques |
| `05-integrations-externes.md` | Services tiers et sourcing |
| `06-ux-ui.md` | Principes d'interface par surface |
| `07-modele-de-donnees.md` | Schéma logique des données |
| `08-chaine-de-mesure.md` | Économie du tracking JSONCargo |
| `09-contrat-api.md` | API JSONCargo (tracking) |
| `10-contrat-api-interne-agent.md` | API interne (surface agent) |
| `11-contrat-api-portail-paiement.md` | API portail client & paiement |
| `12-perspectives-v2.md` | Hors périmètre V1 |
| `etat-du-projet.md` | Point de situation |

Pour lancer le développement : voir [`PROMPT-DEMARRAGE.md`](PROMPT-DEMARRAGE.md).

## Structure du monorepo

```
travess/
├── travess-api/       # backend Laravel 13
├── travess-web/       # front agent React 19 (+ portail client)
├── travess-mobile/    # app React Native
├── travess-desktop/   # shell Electron (réutilise le web)
├── packages/
│   ├── shared-types/  # types TypeScript (miroir de l'API)
│   ├── shared-core/   # logique métier portable
│   └── ui/            # design system partagé
├── docs/              # documentation
├── ops/               # infra, déploiement
└── .claude/           # règles projet + sous-agents de dev
```

## Stack

Laravel 13 · PostgreSQL · Redis · React 19 · React Native · Electron · pnpm + Turborepo.

## Démarrage (dev)

```bash
make setup      # installe les dépendances (composer + pnpm)
make up         # démarre docker (api, postgres, redis, worker, scheduler)
make migrate    # migrations + seeders
```
