/**
 * @travess/shared-types — miroir TypeScript des DTO et enums exposés par l'API.
 *
 * Le contrat d'API est la source de vérité ; ces types en découlent et
 * garantissent l'alignement des clients (web, desktop, mobile). Ils s'étoffent
 * au fil des lots, à mesure que l'API expose de nouvelles ressources.
 */
export * from './enums.js';
export * from './envelope.js';
export * from './tenant.js';
export * from './auth.js';
export * from './client.js';
export * from './dossier.js';
export * from './conteneur.js';
export * from './document.js';
export * from './franchise.js';
export * from './alerte.js';
export * from './dashboard.js';
export * from './audit.js';
