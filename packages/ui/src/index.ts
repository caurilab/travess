/**
 * @travess/ui — design system partagé (web, desktop, portail).
 *
 * Au Lot 0 : les jetons (couleurs, espacements, rayons, ombres, typographie)
 * et le jeu de statuts normalisé. Les composants de base (Button, Input, Badge,
 * Card, Table) seront ajoutés au Lot 1, contre le premier consommateur réel
 * (travess-web), pour être développés et testés en situation plutôt qu'à vide.
 *
 * Feuille de variables CSS : import '@travess/ui/tokens.css'.
 */
export { couleurs, roles } from './tokens/couleurs.js';
export { espacement, ombre, rayon, typographie } from './tokens/echelle.js';
export { aspectPourTon, type AspectStatut, type TonStatut } from './statuts/index.js';
