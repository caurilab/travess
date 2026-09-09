# Travess — Intégrations externes & sourcing des services

> Travess est un produit logiciel : il n'y a pas de matériel à sourcer, mais un ensemble de services tiers dont dépend la V1. Ce document recense chaque dépendance, son rôle, son modèle de coût et les points à sécuriser.

---

## 1. JSONCargo — tracking navire & conteneur

- **Rôle** : suivi automatique des conteneurs (statut, emplacement, ETA) et des navires.
- **Auth** : header `x-api-key`, une clé mutualisée côté API.
- **Modèle de coût** : abonnement mensuel + facturation à l'appel au-delà du quota. Voir barème dans `08-chaine-de-mesure.md`.
- **Couverture** : ~95 % des mouvements maritimes mondiaux ; 11 armateurs nommés au catalogue.
- **Point à sécuriser** :
  - **Grimaldi absent** du catalogue → pas de tracking auto, fallback IMAP.
  - Base URL documentée en **HTTP** dans les exemples — **à confirmer en HTTPS** auprès de l'éditeur avant mise en production (ne jamais transmettre une clé d'API en clair).
- **Démarrage recommandé** : plan intermédiaire (Navigator), mesure réelle via l'endpoint de stats, puis ajustement.

## 2. Provider de vision IA — ingestion documentaire

- **Rôle** : OCR + extraction structurée des documents (BL, factures, DO, déclarations).
- **Accès** : via **Laravel AI SDK** (first-party, stable depuis Laravel 13), en mode **provider-agnostic**. Le provider concret (vision d'un grand modèle) peut être changé sans réécrire le domaine `Ingestion`.
- **Modèle de coût** : à l'appel (par document / par page). Refacturé au tenant via quota + dépassement.
- **Point à sécuriser** : traitement de données potentiellement sensibles (documents commerciaux) → vérifier la politique de rétention du provider, préférer un mode sans conservation.

## 3. Agrégateur Mobile Money — paiements

- **Rôle** : encaissement des charges et honoraires depuis le portail client (Orange Money, MTN MoMo, Wave).
- **Accès** : partenariat avec un **agrégateur agréé** (intermédiaire de paiement licencié BCEAO). Travess ne détient pas de licence propre.
- **Modèle de coût** : commission par transaction (répercutée dans la commission Travess).
- **Points à sécuriser** :
  - Choix de l'agrégateur (couverture des trois réseaux, qualité d'API, délais de reversement).
  - **Conseil juridique local** sur le cadre réglementaire avant mise en production.
  - Gestion des callbacks asynchrones et de la réconciliation.

## 4. WhatsApp Business API — canal client

- **Rôle** : notifications d'étape sortantes, réception de documents entrants (photo → dossier).
- **Accès** : via un fournisseur de solution Business (BSP) ou l'API Cloud officielle.
- **Modèle de coût** : par conversation / par template. Inclus dans les plans supérieurs.
- **Point à sécuriser** : validation des templates de message, numéro d'entreprise vérifié.

## 5. Serveurs IMAP des armateurs — fallback tracking & passerelle

- **Rôle** : source de repli pour le suivi quand le tracking auto n'est pas disponible (Grimaldi, quota atteint) ; passerelle de correspondance transitaire ↔ armateur.
- **Accès** : identifiants IMAP par tenant, stockés chiffrés.
- **Point à sécuriser** : stockage sécurisé des identifiants, heuristique de rattachement des mails au bon dossier.

## 6. Stockage objet — documents

- **Rôle** : stockage des documents déposés (BL, factures, scans).
- **Accès** : service S3-compatible.
- **Point à sécuriser** : chiffrement au repos, URLs signées à durée limitée, cloisonnement par tenant.

## 7. Hébergement & infrastructure

- **API + base + workers** : hébergement supportant PHP 8.3+, PostgreSQL, Redis, workers de queue persistants.
- **Considération locale** : latence vers l'Afrique de l'Ouest ; envisager un hébergement régional ou un CDN pour les surfaces web.

## 8. Récapitulatif des dépendances V1

| Service | Criticité | Fallback |
|---|---|---|
| JSONCargo | Haute (tracking auto) | IMAP + saisie manuelle |
| Vision IA | Haute (ingestion) | Saisie manuelle |
| Agrégateur MoMo | Haute (portail) | Paiement hors ligne enregistré manuellement |
| WhatsApp | Moyenne | Email + push |
| IMAP | Moyenne | Saisie manuelle |
| Stockage objet | Haute | — (indispensable) |
