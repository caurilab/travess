# ADR-002 — Découpage de l'API par domaines (mono-artefact Laravel)

## Contexte
L'API `travess-api` doit être organisée par domaine métier (Tenancy, Identity, Dossiers, Conteneurs, Surestaries, Finances, Transport, Tracking, Ingestion, Portail, Paiements, Messagerie) plutôt qu'en couches techniques (`docs/04` §3.2). Trois options : (a) dossiers de domaines dans un artefact Laravel unique, (b) packages Composer internes séparés, (c) architecture hexagonale complète.

## Décision
Un dossier `app/Domains/<Domaine>/` par domaine, dans le **même artefact Laravel** (autoloadé par la PSR-4 `App\` existante). Chaque domaine porte ses `Models`, `Http` (Controllers/Requests/Resources), `Actions`, `Policies`, `Enums`. Le transverse vit dans `app/Shared/` (contexte tenant, scopes, traits, middleware, base de données). Les routes de chaque domaine sont dans `routes/domains/<domaine>.php`, chargées automatiquement sous `/api/v1` par `routes/api.php` à partir de `config/domains.php`. Les migrations restent centralisées (`database/migrations`) pour garder l'ordre des dépendances FK lisible.

## Alternatives écartées
- **Packages Composer internes** : surcoût de tooling (versions, liens) injustifié pour un mono-artefact déployé d'un bloc. Rejeté au Lot 0 ; réévaluable si un cœur PHP doit être partagé avec un autre service.
- **Hexagonal complet (ports/adapters partout)** : surdimensionné pour l'état actuel ; on garde les conventions Laravel (Eloquent, migrations) sans se battre contre le framework. Les providers externes seront tout de même isolés derrière une couche d'adaptation dans leur domaine (principe n°9).

## Conséquences
- Pas de frontière dure entre domaines au Lot 0 : les interactions passeront par Actions/événements, à consolider au fil des lots.
- La PSR-4 `App\` couvre `App\Domains\…` et `App\Shared\…` sans configuration supplémentaire.
- Le `DomainServiceProvider` centralise le câblage transverse (binding du contexte tenant) et accueillera les providers par domaine.

## Date
2026-09-09
