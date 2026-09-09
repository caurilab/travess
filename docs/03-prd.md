# Travess — Product Requirements Document (PRD)

> Document de référence produit. Version 1 (V1) couvre l'ensemble du périmètre décidé : cœur métier, IA d'extraction, tracking automatique, portail client et paiement Mobile Money. Le PRD décrit *quoi* et *pour qui* ; l'architecture (`04`) décrit *comment*.

---

## 0. Portée de la V1

Décision produit : **tout en phase 1**. Le Mobile Money et l'extraction documentaire par IA sont dans la V1, pas repoussés. Pour livrer sans se disperser, la V1 est découpée en **lots** séquencés (voir §12). Chaque lot laisse un produit utilisable.

Surfaces livrées en V1 : **Web agent**, **Desktop Electron**, **Mobile React Native**, **Portail client web**.

---

## 1. Utilisateurs & rôles

### 1.1 Personas

- **Le gérant** — dirige la société de transit. Veut une vue d'ensemble : dossiers en cours, trésorerie, surestaries évitées, performance des agents.
- **L'agent transit** — traite les dossiers au quotidien. Saisie, suivi, relances, coordination. C'est l'utilisateur principal du poste de travail.
- **L'agent en déplacement** — au port, en douane, chez le client. Utilise surtout le mobile : photo de document, scan de conteneur, consultation.
- **Le chauffeur** — transporte la marchandise. Application mobile réduite : sa mission du jour, sa position partagée, le scan du bon de livraison.
- **L'importateur / le client** — propriétaire de la marchandise. Utilise le portail client : suit ses dossiers, récupère ses documents, paie.
- **L'administrateur de la plateforme** (côté éditeur) — gère les tenants, les plans, la facturation, la supervision.

### 1.2 Matrice des rôles (par tenant)

| Rôle | Dossiers | Finances | Utilisateurs | Paramètres tenant | Portail |
|---|---|---|---|---|---|
| Gérant | Tous (RW) | RW | Gérer | RW | — |
| Agent | Assignés + créer (RW) | Lecture | — | Lecture | — |
| Comptable | Lecture | RW | — | — | — |
| Chauffeur | Mission assignée (R + statut) | — | — | — | — |
| Client | Ses dossiers (lecture) | Ses factures (payer) | — | — | RW |

Rôle plateforme (hors tenant) : **Super-admin éditeur** — accès transverse pour supervision, jamais aux données métier d'un tenant sauf demande de support tracée.

## 2. Concepts métier

### 2.1 Le dossier

Unité centrale. Un dossier représente une opération de transit (un import ou un export). Il porte :

- un **sens** : import ou export ;
- un **client** (le donneur d'ordre) ;
- un ou plusieurs **connaissements (BL)** ;
- un ou plusieurs **conteneurs** ;
- une **chaîne d'étapes** (workflow) ;
- des **documents** ;
- un volet **finances** ;
- un volet **transport** (camion, chauffeur, livraison) ;
- un fil de **communication** (interne, armateur, client).

### 2.2 Les étapes du dossier

Workflow configurable par tenant, avec un modèle par défaut. Étapes types d'un import :

1. Annonce / réception documents
2. Manifeste & arrivée navire
3. Déchargement / disponibilité conteneur
4. Dédouanement
5. Enlèvement conteneur
6. Transport / livraison client
7. Restitution conteneur (vide) à l'armateur
8. Clôture financière

Chaque étape porte un **SLA** (délai cible) éditable, un statut, des dates prévues/réelles, et un responsable.

### 2.3 Le conteneur

- Numéro conforme **ISO 6346** (validation par chiffre de contrôle — logique déjà éprouvée dans le prototype, à porter côté API et côté clients).
- Type (20', 40', 40'HC, reefer…).
- Statut : `à_traiter`, `enlevé`, `livré`, `rendu`.
- Rattachement au navire (nom + IMO).
- Dates de franchise (surestaries, détention) et barèmes par armateur.

### 2.4 Surestaries & détention

- **Surestaries** : frais dus quand le conteneur reste trop longtemps dans l'enceinte portuaire au-delà de la franchise.
- **Détention** : frais dus quand le conteneur (sorti du port) n'est pas restitué à temps à l'armateur.
- Chaque armateur a son **barème** (paliers de jours → tarif/jour). Le barème est paramétrable par tenant.
- Travess calcule en continu le montant en cours et le montant qui **va** tomber, et déclenche les alertes.

### 2.5 Les armateurs

Liste de référence : Maersk, MSC, CMA CGM, Hapag-Lloyd, PIL, COSCO, ONE, Evergreen, Grimaldi, HMM, ZIM, Yang Ming. Chaque armateur porte : son nom normalisé pour l'API de tracking, ses préfixes de conteneur, son barème par défaut, ses paramètres IMAP (fallback).

> **Grimaldi** est très présent en Afrique de l'Ouest mais **absent du catalogue JSONCargo**. Pour Grimaldi, le tracking automatique n'est pas disponible : repli IMAP + saisie manuelle. À signaler dans l'interface.

## 3. Fonctionnalités — cœur métier

### 3.1 Gestion des dossiers
- Création, édition, clôture d'un dossier.
- Vue liste filtrable (sens, statut, client, agent, échéance, alerte).
- Vue détail : étapes, conteneurs, documents, finances, transport, communication.
- Assignation à un ou plusieurs agents.
- Journal d'audit complet (qui a fait quoi, quand).

### 3.2 Suivi des étapes
- Progression étape par étape avec dates prévues/réelles.
- SLA éditables, indicateur de dépassement.
- Motifs de blocage (ex. « attente paiement client ») visibles et traçables.

### 3.3 Gestion des conteneurs
- Saisie manuelle avec validation ISO 6346 en temps réel.
- **Import automatique des numéros depuis un BL** (endpoint JSONCargo BOL → liste de conteneurs, puis contrôle ISO 6346).
- Suivi de statut et d'emplacement.
- Calcul des surestaries/détention par barème armateur.

### 3.4 Alertes surestaries & détention *(différenciateur)*
- Calcul continu des franchises et des montants menaçants.
- Alertes J-3 / J-1 / jour J avant fin de franchise.
- Multi-canal : push mobile, WhatsApp, email, notification desktop native.
- Tableau de bord « argent en train de brûler » : conteneurs à risque, montant cumulé menaçant.
- Métrique de valeur : « surestaries évitées ce mois » (montant que le tenant n'a pas payé grâce aux alertes traitées à temps).

### 3.5 Finances du dossier
- Argent sorti (charges avancées), argent encaissé (remboursements + honoraires).
- Honoraires facturés en PDF avec numérotation légale continue.
- Rapprochement automatique charges avancées ↔ encaissements client.
- Échéancier de trésorerie consolidé (toutes opérations, vue calendrier).

### 3.6 Transport & livraison
- Affectation camion + chauffeur à un dossier.
- Suivi géolocalisé du camion (position transmise par l'app chauffeur).
- Partage d'un lien de suivi en lecture seule au client.
- Scan du bon de livraison à la remise.

### 3.7 Communication
- Fil interne par dossier.
- Passerelle IMAP transitaire ↔ armateur, avec rattachement automatique des mails au dossier (filtrage par objet / n° BL).
- WhatsApp : notifications sortantes et réception de documents entrants (photo → document du dossier).

## 4. Fonctionnalités — Ingestion documentaire par IA *(différenciateur)*

### 4.1 Principe
L'agent dépose un document (PDF ou photo) : BL, facture de charges locales, DO (bon à délivrer), déclaration en douane. Travess :
1. stocke le document ;
2. le passe en file de traitement (queue) ;
3. exécute OCR + extraction structurée via un service de vision (Laravel AI SDK, provider-agnostic) ;
4. produit un brouillon de données (n° BL, conteneurs, armateur, navire, montants, dates de franchise) ;
5. **présente le brouillon à l'agent pour validation** avant toute écriture dans le dossier.

### 4.2 Règles
- **Jamais d'écriture automatique sans validation humaine.** L'IA propose, l'agent dispose.
- Chaque champ extrait affiche son niveau de confiance et la zone source du document.
- Les corrections de l'agent sont conservées (base d'amélioration continue).
- Types de documents et schémas d'extraction paramétrables.
- Consommation IA décomptée par tenant (quota + dépassement).

## 5. Fonctionnalités — Tracking automatique *(différenciateur)*

### 5.1 Principe
Travess interroge l'API **JSONCargo** pour suivre conteneurs et navires sans intervention manuelle. Voir contrats d'API `09-contrat-api.md` et chaîne de mesure `08-chaine-de-mesure.md`.

### 5.2 Règles clés
- Import des conteneurs d'un BL via l'endpoint BOL.
- Rafraîchissement par **scheduler intelligent** : seuls les conteneurs actifs sont interrogés, à une fréquence variable selon la phase (quotidien à l'approche du port et pendant la franchise, hebdomadaire en pleine mer).
- **Plafond de sécurité** : au-delà de ~90 % du quota mensuel, coupure du polling et bascule automatique sur le fallback IMAP.
- Identification d'un navire **toujours par IMO/MMSI**, jamais par le nom seul.
- Grimaldi et tout armateur non couvert : fallback IMAP + manuel.

## 6. Fonctionnalités — Portail client *(différenciateur)*

- Espace client par importateur, rattaché à un tenant.
- Liste et détail de ses dossiers (lecture).
- Téléchargement de ses documents.
- Suivi de livraison (lien géolocalisé).
- **Paiement en ligne des charges et honoraires par Mobile Money** (Orange Money, MTN MoMo, Wave).
- Historique des paiements et reçus.
- Notifications WhatsApp/email des étapes clés.

## 7. Exigences par surface

### 7.1 Web agent
- Poste de travail complet, toutes fonctionnalités cœur.
- Responsive (utilisable sur grand écran d'agence en priorité).
- Rapide sur les listes volumineuses (pagination/virtualisation).

### 7.2 Desktop (Electron)
- **Réutilise le bundle web React** (pas de redéveloppement).
- Valeur ajoutée native, seule justification du desktop :
  - **mode hors-ligne réel** : base locale SQLite, file de synchronisation, reprise à la reconnexion ;
  - **notifications système** natives pour les alertes surestaries ;
  - **impression directe** des documents et factures ;
  - **scan de documents** via périphérique USB ;
  - **raccourcis clavier** de saisie rapide.
- Cible : agent en agence dont la connexion est instable et qui doit continuer à travailler hors-ligne.

### 7.3 Mobile (React Native)
- **Application native distincte** (pas de web embarqué) — les usages sont différents par nature.
- Fonctions : photo de document (→ ingestion IA), scan de numéro de conteneur, géolocalisation chauffeur, notifications push, consultation de dossier, validation d'instructions.
- Mode chauffeur réduit : mission du jour, navigation, partage de position, scan bon de livraison.
- Fonctionne en connexion dégradée (files d'attente, envoi différé).

### 7.4 Portail client (web)
- Interface simple, grand public, mobile-first.
- Priorité au paiement Mobile Money et au suivi.
- Multilingue prêt (français d'abord).

## 8. Exigences transverses

### 8.1 Multi-tenant
- Base de données unique, isolation stricte par `tenant_id` sur chaque enregistrement.
- Aucun accès inter-tenant possible au niveau applicatif (scoping systématique).
- Plans et quotas par tenant.

### 8.2 Sécurité & conformité
- Authentification forte (passkeys disponibles via Fortify en Laravel 13, sinon mot de passe + 2FA).
- Journal d'audit inaltérable par tenant.
- Chiffrement des données sensibles au repos et en transit.
- Traçabilité des accès support de l'éditeur.
- Conformité paiement : partenariat agrégateur agréé (voir `02` et `11`).

### 8.3 Notifications
- Canaux : push (mobile), WhatsApp Business API, email, notification desktop native.
- Préférences par utilisateur et par type d'événement.

### 8.4 Internationalisation
- Français par défaut. Architecture i18n prête pour l'anglais et d'autres langues du corridor.
- Devise FCFA (XOF) par défaut, multidevise possible.

### 8.5 Performance & disponibilité
- Objectif de disponibilité élevé (le suivi surestaries est sensible au temps).
- Traitements lourds (IA, tracking, notifications) en file asynchrone, jamais bloquants pour l'agent.

## 9. Contraintes techniques (résumé)

Détail complet dans `04-architecture.md`. En bref :
- **API** : Laravel 13 (PHP 8.3+), Laravel AI SDK pour l'extraction, PostgreSQL.
- **Web & Portail** : React 19 (React Compiler activé).
- **Desktop** : Electron chargeant le bundle React + couche native.
- **Mobile** : React Native.
- **Monorepo** : un dépôt, dossiers `travess-api`, `travess-web`, `travess-mobile`, `travess-desktop`.

## 10. Hypothèses & décisions figées

| Sujet | Décision |
|---|---|
| Nom | Travess (transit + vessel) |
| Domaine | travess.ci |
| Périmètre V1 | Tout (cœur + IA + tracking + MoMo + portail), séquencé en lots |
| Multi-tenant | Base unique, isolation par `tenant_id` |
| Base de données | PostgreSQL |
| Backend | Laravel 13 + Laravel AI SDK |
| Front web | React 19 |
| Desktop | Electron réutilisant le bundle web |
| Mobile | React Native (natif, distinct) |
| Tracking | JSONCargo mutualisé + fallback IMAP |
| Reprise de données | Le prototype HTML est une maquette de validation, **pas** un système en production → pas de migration de données existantes |

## 11. Hors périmètre V1 (backlog V2)

Voir `12-perspectives-v2.md` : benchmark sectoriel sur données agrégées, produits d'avance de charges / assurance, marketplace transporteurs, application comptable étendue, multidevise avancée.

## 12. Séquencement des lots (V1)

Tout est V1, mais livré dans cet ordre pour qu'un produit soit utilisable au plus tôt :

1. **Lot 0 — Fondations** : monorepo, API Laravel, auth, multi-tenant, modèle de données, design system partagé.
2. **Lot 1 — Cœur dossier (web)** : dossiers, étapes, conteneurs (ISO 6346), documents, audit. *Produit utilisable en agence.*
3. **Lot 2 — Surestaries & alertes** : barèmes armateurs, calcul des franchises, moteur d'alertes multi-canal, tableau de bord.
4. **Lot 3 — Finances** : charges/encaissements, honoraires PDF, rapprochement, échéancier.
5. **Lot 4 — Tracking automatique** : intégration JSONCargo, scheduler, plafond de sécurité, fallback IMAP.
6. **Lot 5 — Ingestion IA** : dépôt, queue, extraction vision, validation humaine.
7. **Lot 6 — Mobile** : app React Native (agent + chauffeur), photo, scan, géoloc, push.
8. **Lot 7 — Portail client + Mobile Money** : espace client, paiement, reçus, notifications.
9. **Lot 8 — Desktop Electron** : packaging, hors-ligne SQLite + sync, natif (impression, scan, notifications).
10. **Lot 9 — WhatsApp** : notifications sortantes, réception documents entrants.

> L'ordre est indicatif ; les lots 4/5 peuvent se paralléliser une fois le cœur stable. Le desktop arrive tard car il dépend d'un web stable.
