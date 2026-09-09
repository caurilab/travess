/**
 * Enums miroir de l'API. Valeurs identiques aux enums PHP (source de vérité :
 * le contrat d'API). Déclarées en tableaux `as const` pour disposer à la fois
 * des valeurs à l'exécution et des types littéraux.
 */

export const ROLES_UTILISATEUR = ['gerant', 'agent', 'comptable', 'chauffeur', 'client'] as const;
export type RoleUtilisateur = (typeof ROLES_UTILISATEUR)[number];

export const PLANS_TENANT = ['essentiel', 'pro', 'business', 'sur_mesure'] as const;
export type PlanTenant = (typeof PLANS_TENANT)[number];

export const STATUTS_TENANT = ['actif', 'suspendu'] as const;
export type StatutTenant = (typeof STATUTS_TENANT)[number];

export const SENS_DOSSIER = ['import', 'export'] as const;
export type SensDossier = (typeof SENS_DOSSIER)[number];
