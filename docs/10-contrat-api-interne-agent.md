# Travess — Contrat d'API interne (surface agent)

> API REST exposée par `travess-api` aux surfaces **web**, **desktop** et **mobile agent**. Versionnée `/api/v1/`. Auth par jeton (Sanctum). Scoping `tenant_id` systématique et non contournable. Ce document donne la forme des ressources et des actions, pas l'implémentation.

---

## 1. Conventions

- Base : `/api/v1`
- Auth : `Authorization: Bearer <token>`. Le `tenant_id` est déduit du token, **jamais** d'un paramètre.
- Réponses : JSON, enveloppe `{ data, meta }`, erreurs normalisées `{ error: { code, message, details } }`.
- Pagination : `?page`, `?per_page`, curseur pour les grandes listes.
- Filtres : query params typés. Idempotence sur les mutations sensibles via clé d'idempotence.
- Tous les DTO ont leur miroir dans `packages/shared-types`.

## 2. Authentification & session
```
POST   /auth/login            → token (+ passkey/2FA si activé)
POST   /auth/logout
GET    /auth/me               → user, role, tenant, permissions
POST   /auth/refresh
```

## 3. Dossiers
```
GET    /dossiers              → liste filtrable (sens, statut, client, agent[, échéance, alerte*])
POST   /dossiers              → créer (référence auto ; options from_bl/from_document* différées)
GET    /dossiers/{id}         → détail complet (étapes, conteneurs, doc, finances*, transport*)
PATCH  /dossiers/{id}         → éditer (statut hors clôture, motif_blocage)
PUT    /dossiers/{id}/agents  → (ré)assigner les agents (endpoint dédié)
POST   /dossiers/{id}/cloturer
GET    /dossiers/{id}/audit   → journal (pagination curseur)
```
> Implémenté au Lot 1. `*` = différé : filtres `échéance`/`alerte` (Lot 2), `from_bl` (Lot 4), `from_document` (Lot 5), résumés `finances` (Lot 3) / `transport` (Lot 4+) — clés présentes à forme figée. L'assignation est un endpoint dédié `PUT /agents` plutôt que via `PATCH`.

## 4. Étapes
```
GET    /dossiers/{id}/etapes
PATCH  /etapes/{id}           → statut, dates, sla, responsable
POST   /dossiers/{id}/etapes/reordonner
```

## 5. Conteneurs & BL
```
POST   /bl                          → créer un BL (dossier, armateur, numero)
POST   /bl/{id}/importer-conteneurs → E2 JSONCargo → liste, contrôle ISO 6346, création
POST   /conteneurs                  → créer (validation ISO 6346 serveur)
GET    /conteneurs/{id}
PATCH  /conteneurs/{id}             → statut, type
GET    /conteneurs/valider?numero=  → validation ISO 6346 (echo côté client aussi via shared-core)
```

## 6. Tracking
```
POST   /conteneurs/{id}/rafraichir-tracking   → force un poll (respecte plafond)
GET    /conteneurs/{id}/tracking              → dernier snapshot + historique
GET    /tracking/etat                         → quota (E10), plafond, mode (auto/fallback)
```

## 7. Surestaries & franchises
```
GET    /conteneurs/{id}/franchises            → surestaries + détention, montants
PATCH  /franchises/{id}                       → ajuster date_debut / jours_francs
GET    /dashboard/argent-en-feu               → conteneurs à risque, montant menaçant cumulé
GET    /dashboard/surestaries-evitees         → métrique de valeur (mois courant)
```

## 8. Alertes
```
GET    /alertes                    → liste (type, statut)
PATCH  /alertes/{id}               → marquer vue / traitée
GET    /alertes/preferences        → canaux par type
PATCH  /alertes/preferences
```

## 9. Documents & ingestion IA
```
POST   /documents                          → upload (web) — multipart
GET    /documents/{id}                      → métadonnées
GET    /documents/{id}/telecharger          → téléchargement du fichier
POST   /documents/photo                    → dépôt photo (mobile) — Lot 6
POST   /documents/{id}/extraire            → met en file l'extraction IA — Lot 5
GET    /documents/{id}/extraction          → statut + champs {valeur, confiance, zone_source} — Lot 5
POST   /documents/{id}/valider-extraction  → applique au dossier (écriture APRÈS validation) — Lot 5
```
> Lot 1 : dépôt, consultation, téléchargement (stockage objet). L'ingestion IA (extraction/validation) est le Lot 5, le dépôt photo mobile le Lot 6.

## 10. Finances
```
GET    /dossiers/{id}/finances     → charges, encaissements, honoraires, solde
POST   /charges                    → argent sorti (avancee_pour_client?)
POST   /encaissements              → argent entré
POST   /honoraires                 → crée + génère facture PDF (numérotation légale)
GET    /honoraires/{id}/pdf
POST   /finances/rapprocher        → rapprochement auto charges ↔ encaissements
GET    /tresorerie/echeancier      → vue consolidée par date
```

## 11. Transport
```
POST   /missions                   → affecter chauffeur + camion à un dossier
PATCH  /missions/{id}              → statut (prévue/en_route/livrée)
POST   /missions/{id}/position     → point GPS (app chauffeur)
GET    /missions/{id}/suivi        → trace + étape
POST   /missions/{id}/lien-public  → génère token de suivi client (lecture seule)
POST   /missions/{id}/bon-livraison→ scan à la remise
```

## 12. Communication
```
GET    /dossiers/{id}/messages     → fil (interne, armateur, client)
POST   /dossiers/{id}/messages     → message interne
POST   /messages/imap/sync         → synchronisation passerelle armateur
POST   /messages/whatsapp/envoyer  → notification sortante (template)
```

## 13. Administration tenant
```
GET    /tenant                     → plan, quotas, consommation
GET    /tenant/consommation        → tracking / IA / whatsapp (mois)
GET    /users                      → gestion (gérant)
POST   /users · PATCH /users/{id}
GET    /armateurs · PATCH /armateurs/{id}   → barèmes, IMAP, nom_api, trackable
GET    /clients · POST /clients
```

## 14. Règles transverses
- Toute action de mutation est **auditée** (`audit_log`).
- Les appels externes (tracking, IA, whatsapp) renvoient un état « en cours » et notifient à l'aboutissement — **jamais bloquants**.
- La validation ISO 6346 est faite **côté serveur** (source de vérité) et **côté client** (retour immédiat) via le même code `shared-core`.
- Le scoping tenant est appliqué au niveau modèle ; aucune route ne l'accepte en paramètre.
