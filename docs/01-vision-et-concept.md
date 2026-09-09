# Travess — Vision & Concept

> **Travess** — contraction de *transit* et *vessel*. Plateforme SaaS multi-tenant pour les entreprises de transit et de transitaires en Afrique de l'Ouest.

---

## 1. Le problème

Un transitaire à Abidjan gère des dizaines de dossiers d'import/export en parallèle. Pour chaque dossier, il doit :

- suivre le connaissement (BL) étape par étape, du chargement à la livraison finale ;
- surveiller les conteneurs, leur statut, leur emplacement, le navire qui les porte ;
- anticiper les **surestaries** (frais de retard sur conteneur en port) et la **détention** (frais de retard hors port), qui tombent dès qu'une franchise expire et coûtent cher ;
- coordonner le dédouanement ;
- avancer des charges pour le compte du client, puis se faire rembourser et facturer ses honoraires ;
- organiser le transport routier jusqu'à l'entrepôt du client ;
- répondre en continu aux clients qui demandent « où en est ma marchandise ? ».

Aujourd'hui, tout cela vit dans des messageries, des tableurs, des carnets et la tête des agents. Les conséquences : surestaries subies faute d'anticipation, erreurs de saisie sur les numéros de conteneur, trésorerie illisible, clients anxieux, et une dépendance forte à la mémoire des personnes.

## 2. La proposition de valeur

Travess centralise le cycle de vie complet d'un dossier de transit et transforme trois pertes chroniques en avantages :

1. **La saisie manuelle devient de l'ingestion.** L'agent dépose un BL, une facture de charges, une déclaration en douane — Travess lit le document, en extrait les données et pré-remplit le dossier. Quelques minutes gagnées sur chaque dossier, des erreurs de recopie évitées.

2. **Les surestaries subies deviennent des surestaries évitées.** Travess connaît les dates de franchise et alerte l'agent avant que les frais ne tombent — avec le montant exact qui menace. La plateforme sait chiffrer « X FCFA de surestaries évitées ce mois », un retour sur investissement mesurable.

3. **Le client anxieux devient un client autonome.** Un portail dédié où l'importateur suit ses dossiers, récupère ses documents, paie ses charges et honoraires par Mobile Money, et reçoit ses notifications par WhatsApp.

## 3. Les différenciateurs

- **Ingestion documentaire par IA** — OCR et extraction structurée à partir de photos ou PDF, avec validation humaine avant écriture.
- **Moteur d'alertes proactif** sur les surestaries et la détention, chiffré, multi-canal (push, WhatsApp, email).
- **Portail client transactionnel** avec paiement Mobile Money intégré (Orange, MTN, Wave).
- **Tracking navire et conteneur automatique** via l'API JSONCargo, avec repli sur la messagerie armateur (IMAP) pour les cas non couverts.
- **WhatsApp comme canal natif** — le canal réellement utilisé par les clients sur ce marché.

## 4. Le marché

Cible primaire : les entreprises de transit d'Abidjan, puis le corridor UEMOA (Côte d'Ivoire, Burkina Faso, Mali, Niger, Sénégal, Bénin, Togo) et l'Afrique de l'Ouest élargie. Le produit est pensé multi-tenant dès l'origine : chaque société cliente dispose de son espace isolé, de ses utilisateurs, de son plan d'abonnement.

## 5. Les surfaces

Travess se décline sur quatre surfaces qui partagent le même cœur métier :

| Surface | Public | Rôle |
|---|---|---|
| **Web** (agent) | Transitaires, agents | Poste de travail complet en agence |
| **Desktop** (Electron) | Transitaires, agents | Même poste, avec mode hors-ligne et fonctions natives (impression, scan, notifications système) |
| **Mobile** (React Native) | Agents en déplacement, chauffeurs | Photo de documents, géolocalisation, scan de conteneur, notifications push |
| **Portail client** (web) | Importateurs / exportateurs | Suivi, documents, paiement Mobile Money |

## 6. Ce que Travess n'est pas

- Ce n'est pas un logiciel de comptabilité complet — il gère la trésorerie d'un dossier, pas le grand livre de l'entreprise.
- Ce n'est pas un TMS de transporteur routier — il coordonne le transport, il ne gère pas une flotte.
- Ce n'est pas un système douanier officiel — il recoupe et suit le dédouanement, il ne remplace pas le guichet unique.
