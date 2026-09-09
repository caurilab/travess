# ADR-006 — Workflow d'étapes : modèle par défaut en code + snapshot à la création

## Contexte
Un dossier porte une chaîne d'étapes, « configurable par tenant, avec un modèle par défaut » (docs/03 §2.2, docs/07). Il fallait choisir comment définir le modèle par défaut, permettre une configuration par tenant, et instancier les étapes d'un dossier.

## Décision
- **Modèle par défaut en code** : `App\Domains\Dossiers\Support\ModeleWorkflow` expose, par `SensDossier`, une liste ordonnée `{libelle, sla_jours}` (les 8 étapes import de docs/03 §2.2 ; un miroir adapté pour l'export). SLA indicatifs, éditables.
- **Surcharge par tenant sans nouvelle table** : la résolution lit d'abord `tenant.parametres->workflow->{import|export}` (jsonb déjà présent), sinon retombe sur le défaut code. Le paramétrage fin (UI + éventuelle table dédiée) devient un incrément ultérieur **sans migration**.
- **Instanciation = snapshot** : à la création d'un dossier, les étapes du modèle résolu sont **copiées** en lignes `etapes` réelles (ordre, libellé, sla_jours, statut `a_faire`, `date_prevue` dérivée du cumul des SLA). Chaque dossier possède ses étapes, éditables sans réécrire l'historique ; modifier un futur modèle n'altère pas les dossiers existants.

## Alternatives écartées
- **Table de config `modeles_etape` par tenant dès le Lot 1** : mécanique de paramétrage sans UI pour l'exploiter. Reportée.
- **Étapes référençant un template vivant** : un changement de modèle réécrirait l'historique. Rejeté au profit du snapshot.

## Conséquences
- Aucune migration pour le workflow au Lot 1 (la table `etapes` per-dossier suffit).
- La bascule vers une table de config, si le produit l'exige, ne casse pas la structure (le seam de résolution est déjà isolé).

## Date
2026-09-10
