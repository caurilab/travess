# Travess — Modèle économique

---

## 1. Les sources de revenu

Travess combine plusieurs leviers, du plus simple au plus ambitieux. Les premiers financent le produit ; les suivants se déploient une fois la base installée.

### 1.1 Abonnement par plan (socle)

Abonnement mensuel ou annuel par société cliente, structuré par paliers. Chaque palier ouvre un nombre de sièges (utilisateurs), un volume d'usage inclus (extractions IA, appels de tracking) et un ensemble de fonctionnalités.

| Plan | Cible | Sièges | Extractions IA / mois | Tracking conteneur | Portail client | Paiement MoMo |
|---|---|---|---|---|---|---|
| **Essentiel** | Petit transitaire | 3 | 50 | Manuel (IMAP) | — | — |
| **Pro** | Transitaire établi | 10 | 300 | Automatique inclus | Oui | Oui |
| **Business** | Multi-agences | 30 | 1 000 | Automatique inclus | Oui | Oui |
| **Sur-mesure** | Groupe | négocié | négocié | négocié | Oui | Oui |

> Les chiffres ci-dessus sont des points de départ à calibrer sur le terrain, pas des valeurs figées.

### 1.2 Commission sur les paiements (revenu d'usage)

Les charges et honoraires payés par les importateurs via le portail (Mobile Money) transitent par Travess. Une commission est prélevée sur chaque transaction. Ce revenu croît avec l'usage réel de la plateforme, indépendamment du nombre de sièges.

> **Cadre réglementaire.** Le prélèvement sur paiement passe par un partenariat avec un agrégateur Mobile Money agréé (type intermédiaire de paiement licencié BCEAO), pas par une licence propre. À sécuriser avec un conseil juridique local avant la mise en production de ce volet.

### 1.3 Facturation à l'usage sur l'IA (dépassement)

Chaque plan inclut un quota d'extractions documentaires. Au-delà, facturation à l'unité. Le coût marginal d'une extraction (appel au modèle de vision) est faible ; la marge est confortable et le revenu s'ajuste à l'intensité d'usage.

### 1.4 Donnée agrégée & produits dérivés (moyen terme)

Une fois la volumétrie installée, les données anonymisées et agrégées deviennent un actif : délais réels par étape, par armateur, par corridor ; taux de surestaries ; temps de dédouanement par circuit. De là peuvent naître un **benchmark sectoriel** vendable, ou des produits d'**avance de charges** / **assurance marchandise** en partenariat.

> Ces pistes touchent à des activités réglementées (assurance, crédit). Elles ne se lancent qu'avec les partenaires et agréments adéquats.

### 1.5 Marketplace & partenariats (moyen terme)

Mise en relation avec transporteurs routiers, cabinets de dédouanement, assureurs, avec commission d'apport.

## 2. La structure de coûts variables

| Poste | Nature | Maîtrise |
|---|---|---|
| API tracking JSONCargo | Abonnement mutualisé + appels | Scheduler intelligent + plafond de sécurité (voir `08-chaine-de-mesure.md`) |
| Extraction IA (vision) | À l'appel | Quota par plan, refacturation au dépassement |
| Mobile Money (frais agrégateur) | % par transaction | Répercuté dans la commission |
| WhatsApp Business API | Par conversation | Inclus dans les plans supérieurs |
| Infrastructure (hébergement, stockage) | Fixe + palier | Dimensionnement progressif |

## 3. Le principe directeur

Le socle d'abonnement doit couvrir les coûts fixes et le coût de service d'un tenant. Les revenus d'usage (commission paiement, dépassement IA) sont la marge de croissance. Les produits dérivés sont un pari de seconde étape, à ne pas laisser retarder le lancement du cœur.

## 4. Point d'attention JSONCargo

Un **seul** abonnement JSONCargo est mutualisé sur l'ensemble des tenants. Le coût réel par conteneur suivi sur son cycle de vie se compte en centimes d'euro. La consommation est néanmoins **journalisée par tenant** côté Travess, afin de dimensionner les futurs paliers de prix et de facturer le tracking en option sur les plans qui ne l'incluent pas. Voir `08-chaine-de-mesure.md` pour la mécanique.
