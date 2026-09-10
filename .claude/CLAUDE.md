# Travess — Règles projet pour l'assistant de code

> À lire avant toute intervention. Ce fichier fixe le cadre non négociable. La documentation complète est dans `docs/`.

## Le projet en une phrase
Travess (transit + vessel) : SaaS multi-tenant pour les transitaires d'Afrique de l'Ouest — suivi de dossiers, surestaries, tracking conteneur/navire, ingestion documentaire par IA, portail client avec paiement Mobile Money. Surfaces : web, desktop (Electron), mobile (React Native), portail client.

## Stack (figé)
- API : Laravel 13 (PHP 8.3+), Laravel AI SDK, PostgreSQL, Redis, queues.
- Web & portail : React 19 (React Compiler activé), TypeScript strict.
- Desktop : Electron réutilisant le bundle web + couche native (SQLite hors-ligne, impression, scan, notifications).
- Mobile : Capacitor réutilisant le bundle web + couche native (caméra/scan, notifications). Voir ADR-015 (amende le principe n°8 ; on abandonne React Native).
- Monorepo : pnpm workspaces + Turborepo. Laravel dans le dépôt mais hors workspaces pnpm.

## Principes non négociables
1. Le cœur métier s'écrit une fois dans `packages/shared-core` (ISO 6346, surestaries/détention, prochain poll) et se réutilise partout.
2. Le contrat d'API est la source de vérité ; `packages/shared-types` en découle.
3. Le scoping `tenant_id` n'est jamais optionnel ni fourni par le client — il vient du token.
4. Tout traitement lourd (IA, tracking, notifications, PDF) passe en queue. L'agent n'attend jamais un appel externe.
5. L'IA propose, l'humain valide — jamais d'écriture automatique depuis l'extraction.
6. Le tracking respecte le plafond de sécurité (~90 % du quota) et bascule sur IMAP. Jamais de polling aveugle.
7. Un navire s'identifie par IMO/MMSI, jamais par son nom.
8. Le desktop et le mobile réutilisent le bundle web ; chacun ajoute sa couche native (Electron pour le desktop, Capacitor pour le mobile). Cf. ADR-001 et ADR-015.
9. Chaque provider externe est isolé derrière une couche d'adaptation dans son domaine.

## Langue & style
- Code, commentaires, commits, documentation : en français.
- Prose sobre, factuelle, sans emphase ni auto-promotion.

## Sous-agents disponibles (`.claude/agents/`)
architecte · auditeur-securite · archiviste · committeur · testeur · chef-de-projet · reviewer · integrateur-externe.
Convoque-les selon le travail : architecture avant de structurer, auditeur avant de toucher auth/paiement/tenant, testeur sur le métier critique, reviewer avant fusion, etc.

## Ordre de lecture de la doc
1. `docs/01-vision-et-concept.md`
2. `docs/03-prd.md` (référence produit)
3. `docs/04-architecture.md`
4. `docs/07-modele-de-donnees.md`
5. `docs/08-chaine-de-mesure.md` + `docs/09-contrat-api.md` (tracking)
6. `docs/10` + `docs/11` (contrats d'API internes)
7. `docs/etat-du-projet.md` (où on en est)

## Dépendances externes à sécuriser avant prod
- JSONCargo : confirmer HTTPS, statut Grimaldi.
- Mobile Money : agrégateur agréé + conseil juridique local.
