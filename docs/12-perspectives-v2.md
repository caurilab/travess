# Travess — Perspectives V2

> Ce qui est délibérément hors du périmètre V1. Consigné ici pour ne pas polluer la V1 tout en gardant le cap. Rien ici ne doit retarder le lancement du cœur.

---

## 1. Données agrégées & benchmark sectoriel

Une fois la volumétrie installée, les snapshots de tracking et les temps réels par étape deviennent un actif analytique :
- délais réels par étape / armateur / corridor ;
- taux de surestaries par circuit ;
- temps de dédouanement par régime.

Produit envisagé : un **benchmark sectoriel anonymisé** vendable aux transitaires, aux chargeurs, voire aux autorités portuaires. Prérequis : anonymisation robuste, consentement, masse critique de données.

## 2. Produits financiers dérivés

- **Avance de charges** : Travess (ou un partenaire) avance les charges portuaires, remboursées à l'encaissement client.
- **Assurance marchandise** en marque blanche.

> Activités **réglementées** (crédit, assurance). Ne se lancent qu'avec les agréments et partenaires adéquats et un conseil juridique. Pas avant une base installée et une donnée fiable.

## 3. Marketplace & partenariats

Mise en relation avec commission d'apport :
- transporteurs routiers ;
- cabinets de dédouanement ;
- assureurs marchandise ;
- entrepôts.

## 4. Comptabilité étendue

La V1 gère la trésorerie **par dossier**. Une V2 pourrait offrir une comptabilité société plus complète (plan comptable, export vers logiciels comptables locaux, TVA).

## 5. Couverture tracking élargie

- Intégrer **Grimaldi** dès qu'une source fiable existe (API dédiée, EDI, ou accord direct) — enjeu fort en Afrique de l'Ouest.
- Sources complémentaires (autres agrégateurs) pour combler les trous de couverture.

## 6. Multidevise & multi-pays avancé

- Gestion fine multidevise (au-delà du FCFA), taux, refacturation.
- Adaptations réglementaires par pays du corridor.

## 7. Amélioration continue de l'IA

- Boucle d'apprentissage sur les corrections d'agents (`extraction_ia.corrections`) pour améliorer l'extraction.
- Nouveaux types de documents.
- Extraction de bout en bout avec moins de validation à mesure que la confiance monte (toujours avec garde-fou humain).

## 8. Écosystème & API publique

- API publique Travess pour que des tiers (ERP client, douane, transporteurs) s'intègrent.
- Webhooks sortants pour les clients avancés.
