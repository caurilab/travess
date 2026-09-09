# ADR-011 — Jobs tenant-scopés, scheduler et moteur d'alertes découplé

## Contexte
Le Lot 2 introduit les premiers traitements en file et le premier scheduler. Le multi-tenant est fail-closed : un modèle scopé ne peut être touché sans contexte (RLS + TenantScope). Il fallait un cadre pour que les jobs respectent l'isolation, et découpler la génération d'alertes de leur envoi (les canaux réels arrivent aux lots 6/8/9).

## Décision
**Porteur de contexte tenant** : `TenantContext::pour($tenantId, $callback)` établit le contexte (mémoire + GUC PostgreSQL `app.tenant_id`) puis restaure l'état précédent (imbrication sûre). Base `App\Shared\Jobs\JobTenantScoped` : sérialise le seul `tenant_id`, rétablit le contexte à l'exécution → RLS, TenantScope, forçage de `tenant_id` et audit se comportent comme en HTTP. Résorbe la dette « contexte tenant en file ».

**Orchestration** : la commande `surestaries:rafraichir` énumère les tenants (racine non scopée) et dispatche **un job par tenant** ; jamais de boucle inter-tenant unique mutant des lignes scopées. Planifiée quotidiennement (tôt le matin pour que J0 tombe en début de journée). Le job recalcule/persiste les franchises puis génère les alertes.

**Moteur d'alertes** : génération idempotente (une alerte par `(tenant, conteneur, type)` via unicité + `firstOrCreate`), seuils **J-3 / J-1 / J0** avant fin de franchise, `montant_menacant` figé sur l'alerte.

**Découplage génération / envoi** : la génération dispatche `EnvoyerAlerte` (job par alerte). L'envoi passe par des adaptateurs `CanalEnvoi` (principe n°9) : **e-mail réel** (en file), et **canaux différés** (push=Lot 6, desktop=Lot 8, WhatsApp=Lot 9) qui journalisent l'intention sans envoyer. Chaque `(destinataire, canal)` est tracé dans `notification` ; les lots ultérieurs ne remplacent que l'adaptateur. Tout envoi en file (principe n°4).

## Alternatives écartées
- Boucle inter-tenant unique dans un job : risque de fuite / de contexte incohérent. Rejetée au profit d'un job par tenant.
- Génération et envoi couplés (envoi en ligne) : viole le principe n°4 et couple le moteur aux canaux. Rejeté.

## Conséquences
- Tout futur traitement en file hérite de `JobTenantScoped`.
- L'ajout d'un canal réel = un nouvel adaptateur, sans toucher au moteur.

## Date
2026-09-10
