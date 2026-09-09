/**
 * @travess/shared-core — logique métier portable, écrite une seule fois côté
 * TypeScript et réutilisée par les clients (web, desktop, mobile).
 *
 * Au Lot 0, seule la validation ISO 6346 y vit (logique éprouvée). Le calcul
 * des surestaries/détention (Lot 2) et le calcul de prochain poll (Lot 4)
 * viendront s'y ajouter, avec pour chacun des vecteurs de test partagés
 * garantissant la parité avec le port PHP de l'API.
 */
export * from './iso6346/index.js';
