# ADR-003 — Parité du cœur métier entre PHP (API) et TypeScript (clients)

## Contexte
Le principe non négociable n°1 impose que le cœur métier (validation ISO 6346, calcul surestaries/détention, prochain poll) soit « écrit une fois dans `packages/shared-core` et réutilisé partout ». Or ce cœur doit s'exécuter **côté serveur PHP** (source de vérité, persistance) *et* **côté clients TypeScript** (retour immédiat, hors-ligne). Deux runtimes distincts : un unique paquet TypeScript ne peut pas être invoqué depuis PHP à un coût raisonnable. Le « écrit une fois » n'est donc pas littéralement réalisable au niveau de l'implémentation.

## Décision
Ce qu'on écrit une seule fois n'est pas l'implémentation mais la **spécification exécutable** :
1. **Vecteurs de test partagés** : un fichier unique par règle (`packages/test-vectors/iso6346.json`, puis `surestaries.json`, `poll.json`) est la source de vérité comportementale.
2. **Deux ports minces** : un port TypeScript (`packages/shared-core`) et un port PHP (dans le domaine concerné, ex. `app/Domains/Conteneurs/Support/Iso6346.php`).
3. **Parité testée, pas supposée** : les deux batteries (Vitest côté TS, PHPUnit côté PHP) consomment **le même** fichier de vecteurs. Toute divergence casse la CI.

Appliqué au Lot 0 pour l'ISO 6346 : 29 cas partagés (valides, invalides, chiffres de contrôle), verts des deux côtés.

## Alternatives écartées
- **Service PHP appelé par les clients pour toute validation** : casse le retour immédiat et le hors-ligne (desktop/mobile). Rejeté.
- **Compilation du TS en WASM chargé par PHP** : complexité et fragilité disproportionnées. Rejeté.
- **Supposer la parité sans la tester** : la dérive est inévitable. Rejeté.

## Conséquences
- Toute nouvelle règle métier portable se livre en trois temps : vecteurs partagés, port TS, port PHP — les trois dans le même lot.
- La CI doit exécuter les deux suites ; un écart de comportement est un échec bloquant.
- Nuance assumée du principe n°1 : « une seule source de vérité **comportementale** », deux implémentations vérifiées contre elle.

## Date
2026-09-09
