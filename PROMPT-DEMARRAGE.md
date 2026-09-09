# Travess — Prompt de démarrage pour l'agent de code

> À fournir à l'agent de développement (Claude Code) pour lancer la construction. Ce prompt cadre la mission, l'ordre de travail et les règles. La documentation détaillée est dans `docs/` — l'agent doit la lire avant de coder.

---

## Ta mission

Tu construis **Travess**, un SaaS multi-tenant pour les entreprises de transit et transitaires d'Afrique de l'Ouest. Tu travailles dans ce monorepo. Avant d'écrire la moindre ligne, lis dans l'ordre : `.claude/CLAUDE.md`, `docs/01-vision-et-concept.md`, `docs/03-prd.md`, `docs/04-architecture.md`, `docs/07-modele-de-donnees.md`, puis les contrats d'API (`docs/08`, `09`, `10`, `11`).

## Comment tu travailles

1. **Tu procèdes par lots** (voir `docs/03-prd.md` §12). Tu ne démarres pas un lot sans avoir terminé et fait relire le précédent. Chaque lot laisse un produit démontrable.
2. **Tu convoques les sous-agents** définis dans `.claude/agents/` au bon moment :
   - `architecte` avant toute décision de structure ou de contrat ;
   - `auditeur-securite` dès que tu touches à l'auth, l'isolation tenant, les secrets ou les paiements ;
   - `integrateur-externe` pour JSONCargo, l'IA, le Mobile Money, WhatsApp, l'IMAP ;
   - `testeur` pour couvrir le métier critique ;
   - `reviewer` avant chaque fusion ;
   - `committeur` pour la discipline git ;
   - `archiviste` pour tenir `docs/etat-du-projet.md` et les ADR à jour ;
   - `chef-de-projet` pour arbitrer la portée et éviter la dispersion.
3. **Tu respectes les principes non négociables** de `.claude/CLAUDE.md`. En cas de doute, tu t'y réfères plutôt que d'improviser.
4. **Tu écris en français** : code, commentaires, commits, docs.
5. **Tu poses une question** plutôt que de deviner sur un point structurant non tranché.

## Le stack (déjà figé — ne le remets pas en question)

- API : Laravel 13 (PHP 8.3+), Laravel AI SDK, PostgreSQL, Redis, queues.
- Web & portail client : React 19 (React Compiler), TypeScript strict.
- Desktop : Electron réutilisant le bundle web + couche native.
- Mobile : React Native (natif, distinct).
- Monorepo : pnpm workspaces + Turborepo ; Laravel hors workspaces pnpm.

## Ordre de construction (les lots)

**Lot 0 — Fondations.** Mets en place le monorepo (pnpm workspaces, Turborepo, `travess-api`, `travess-web`, `travess-mobile`, `travess-desktop`, `packages/shared-types`, `packages/shared-core`, `packages/ui`). API Laravel 13 avec auth (Sanctum, passkeys/2FA via Fortify), multi-tenant (base unique, scoping `tenant_id` au niveau modèle + garde-fou), modèle de données de `docs/07`, design system de base dans `packages/ui`. Docker de dev (api, postgres, redis, worker, scheduler). CI de base.

**Lot 1 — Cœur dossier (web).** Dossiers, étapes/workflow, conteneurs avec validation ISO 6346 (dans `shared-core`), BL, documents, journal d'audit. Interface agent web. → *Produit utilisable en agence.*

**Lot 2 — Surestaries & alertes.** Barèmes armateurs, calcul des franchises et montants (dans `shared-core`), moteur d'alertes multi-canal, tableaux de bord « argent en train de brûler » et « surestaries évitées ».

**Lot 3 — Finances.** Charges, encaissements, honoraires (facture PDF, numérotation continue), rapprochement automatique, échéancier de trésorerie.

**Lot 4 — Tracking automatique.** Intégration JSONCargo (`docs/09`), import conteneurs depuis BL, scheduler intelligent avec `prochain_poll_prevu`, plafond de sécurité via `/api_key/stats`, fallback IMAP, journalisation de consommation par tenant (`docs/08`).

**Lot 5 — Ingestion IA.** Dépôt document, queue, extraction via Laravel AI SDK, écran de validation humaine (document + champs + confiance), application au dossier après validation seulement.

**Lot 6 — Mobile.** App React Native : profil agent (photo → ingestion, scan conteneur, consultation, push) et profil chauffeur (mission, position, scan bon de livraison). Résilience réseau.

**Lot 7 — Portail client + Mobile Money.** Espace client cloisonné (`docs/11`), suivi, documents, paiement via agrégateur agréé (webhook signé, idempotence, réconciliation, commission, reçu), notifications.

**Lot 8 — Desktop Electron.** Packaging Electron réutilisant le bundle web, mode hors-ligne (SQLite + file de sync + résolution de conflits), notifications système, impression directe, scan USB, raccourcis clavier, mise à jour auto.

**Lot 9 — WhatsApp.** Notifications sortantes (templates), réception de médias entrants rattachés au dossier.

## Points de vigilance à ne pas oublier

- **Isolation tenant** : c'est la faille la plus grave. Teste-la explicitement.
- **JSONCargo** : nom d'armateur obligatoire en paramètre ; navire identifié par IMO/MMSI ; Grimaldi non couvert (fallback) ; confirmer HTTPS avant d'envoyer la clé ; ne jamais poller en boucle aveugle.
- **IA** : jamais d'écriture sans validation humaine.
- **Paiement** : webhook signé, idempotent, réconcilié ; cadre réglementaire via agrégateur agréé.
- **shared-core** : toute logique métier portable (ISO 6346, surestaries, poll) y vit une seule fois.

## Ta première action

Lis la documentation, puis propose un plan détaillé du **Lot 0** (structure exacte des dossiers, migrations, mise en place de l'auth et du multi-tenant) et attends validation avant de coder. Convoque `architecte` pour ce plan.
