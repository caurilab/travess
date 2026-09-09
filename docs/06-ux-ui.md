# Travess — UX / UI

> L'interface du prototype HTML ne sera **pas** reprise. Ce document pose les principes, pas des maquettes figées. Objectif : un poste de travail dense mais lisible pour l'agent, et des surfaces client/chauffeur simples.

---

## 1. Principes directeurs

1. **La densité au service de l'agent.** L'agent traite beaucoup de dossiers : listes rapides, filtres puissants, actions au clavier, peu de clics. On assume une interface professionnelle dense, pas un tableau de bord grand public.
2. **L'urgence est visible.** Les surestaries menaçantes, les SLA dépassés, les blocages remontent en tête, avec un code couleur cohérent (à risque / en cours / réglé).
3. **La valeur se chiffre.** Le montant menaçant et les surestaries évitées sont affichés, pas cachés — c'est l'argument de rétention.
4. **Le client, lui, veut la simplicité.** Le portail et l'app chauffeur vont à l'essentiel : où en est ma marchandise, que dois-je payer, quelle est ma mission.
5. **Une identité, trois écrans.** Web, desktop et portail partagent le design system (`packages/ui`). Le mobile s'aligne visuellement mais suit les conventions natives.

## 2. Design system partagé

- Tokens communs : couleurs, typographie, espacements, rayons, ombres.
- Composants de base : boutons, champs, tables, badges de statut, cartes de dossier, indicateurs d'alerte, fils de discussion.
- Un jeu de statuts normalisé réutilisé partout (conteneur, étape, paiement).
- Accessibilité : contrastes suffisants, cibles tactiles correctes sur mobile, navigation clavier sur web/desktop.

## 3. Surface web agent

### 3.1 Structure
- **Barre latérale** : dossiers, alertes, finances, transport, paramètres.
- **Tableau de bord d'entrée** : conteneurs à risque (argent en train de brûler), SLA dépassés, dossiers bloqués, activité récente, indicateur « surestaries évitées ce mois ».
- **Liste des dossiers** : filtrable (sens, statut, client, agent, échéance, alerte), triable, avec badges d'alerte visibles en ligne.
- **Détail dossier** en onglets : Étapes · Conteneurs · Documents · Finances · Transport · Communication · Journal.

### 3.2 Écrans clés
- **Nouveau dossier** avec option « importer depuis un BL » (déclenche l'import conteneurs JSONCargo) et « déposer un document » (déclenche l'ingestion IA).
- **Validation d'extraction IA** : document à gauche, champs extraits à droite avec niveau de confiance, l'agent corrige et valide.
- **Conteneur** : saisie avec validation ISO 6346 en direct, barème armateur, compte à rebours de franchise, montant menaçant.
- **Finances du dossier** : charges avancées, encaissements, honoraires, rapprochement, bouton génération facture PDF.

## 4. Surface desktop (Electron)

- **Même interface que le web**, avec des ajouts natifs discrets :
  - bandeau **hors-ligne** clair quand la connexion tombe, indicateur de synchronisation en attente ;
  - **notifications système** pour les alertes surestaries même app fermée ;
  - bouton **imprimer** direct sur documents et factures ;
  - action **scanner** un document depuis un périphérique ;
  - **raccourcis clavier** affichés et personnalisables.

## 5. Surface mobile (React Native)

### 5.1 Profil agent
- Accueil : mes dossiers, mes alertes.
- **Photographier un document** → part en ingestion IA.
- **Scanner un numéro de conteneur** (OCR/code-barres) avec validation ISO 6346.
- Consulter un dossier, valider une instruction.
- Notifications push des alertes.

### 5.2 Profil chauffeur
- **Ma mission du jour** : dossier, adresse, marchandise.
- **Partager ma position** (suivi live pour l'agent et le client).
- **Scanner le bon de livraison** à la remise.
- Interface épurée, gros boutons, utilisable d'une main.

## 6. Surface portail client (web, mobile-first)

- Connexion simple.
- **Mes dossiers** : liste et suivi visuel de l'avancement.
- **Suivi de livraison** : carte, position du camion, étape en cours.
- **Mes documents** : téléchargement.
- **Payer** : charges et honoraires dus, paiement Mobile Money en quelques taps, reçu immédiat.
- **Notifications** : préférences WhatsApp / email.

## 7. États & retours

- Chargements : squelettes plutôt qu'écrans vides.
- Files hors-ligne : indication claire de ce qui est en attente d'envoi.
- Erreurs : messages actionnables, jamais de code technique brut face au client.
- Actions asynchrones (IA, tracking, paiement) : retour immédiat « en cours », notification à l'aboutissement.

## 8. Ce que l'on évite

- Reproduire la maquette HTML actuelle telle quelle.
- Surcharger le portail client d'informations d'agent.
- Bloquer l'agent pendant un appel externe (tout passe en asynchrone visible).
