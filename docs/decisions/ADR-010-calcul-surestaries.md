# ADR-010 — Calcul des surestaries/détention et métrique « surestaries évitées »

## Contexte
Le différenciateur du produit (« argent en train de brûler ») repose sur un calcul de franchises fiable et sur une métrique de valeur défendable. Plusieurs points de sémantique devaient être tranchés.

## Décision
**Barème** (stocké dans `armateur.bareme_surestaries` / `bareme_detention`, format canonique) : paliers `de_jour..a_jour → tarif_jour`, **par type de conteneur** (`20`, `40`, `40hc`, `reefer`, repli `defaut`), devise **XOF**, **montants entiers** (le franc CFA n'a pas de sous-unité → colonnes `numeric(15,0)`, casts entiers).

**Facturation progressive** : chaque jour facturé est compté au tarif du palier où il tombe ; le total est la somme des tarifs journaliers.

**Décompte** (jours **calendaires**) : `date_debut` = jour 1 de franchise ; `date_fin_franchise = date_debut + jours_francs − 1` (dernier jour gratuit) ; `premier_jour_facture = date_fin_franchise + 1` ; **jour entamé = jour dû**. `montant_en_cours` = cumul des jours facturés jusqu'à la date d'évaluation ; `montant_menacant` = coût projeté sur un **horizon de 3 jours** (aligné sur l'alerte J-3), calculé même pendant la franchise (anticipation).

**Activité** : `surestaries` active tant que le conteneur est `à_traiter` ; `détention` active quand il est `enlevé`/`livré` ; `rendu` → inactif.

**Gel à la sortie** : dès qu'une franchise devient inactive (conteneur sorti), `montant_menacant` passe à 0 et `montant_en_cours` est **figé** à sa dernière valeur (coût réellement accumulé). C'est la base de la métrique.

**Parité PHP↔TS** (ADR-003) : port TS (`packages/shared-core/src/surestaries`) + port PHP (`app/Domains/Surestaries/Support/CalculFranchise`), vecteurs partagés `packages/test-vectors/surestaries.json`.

**« Surestaries évitées ce mois » — métrique basée sur preuve** : pour un conteneur ayant reçu une alerte surestaries/détention ce mois et **sorti à temps** (franchise inactive), `évité = montant_menaçant figé sur l'alerte − coût réellement accumulé` (planché à 0). La preuve est la sortie du conteneur (auditée) ; on rejette « somme des alertes traitées » (non probant).

## Alternatives écartées
- Facturation forfaitaire (toute la période au palier atteint) : moins conforme à la pratique. Rejetée.
- Montants en `decimal:2` : inadapté au XOF. Corrigé en entiers.
- Métrique « alertes traitées » : gonflable, non probante. Rejetée.

## Conséquences
- Recalcul hybride : à l'écriture (Actions), en lecture (frais, en mémoire), et quotidien (job planifié, persisté) — tous via le port unique.
- Horizon menaçant fixe à 3 j au Lot 2 (configurable ultérieurement).

## Date
2026-09-10
