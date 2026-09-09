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

export const STATUTS_DOSSIER = ['ouvert', 'en_cours', 'bloque', 'cloture'] as const;
export type StatutDossier = (typeof STATUTS_DOSSIER)[number];

export const STATUTS_ETAPE = ['a_faire', 'en_cours', 'fait', 'en_retard'] as const;
export type StatutEtape = (typeof STATUTS_ETAPE)[number];

export const STATUTS_CONTENEUR = ['a_traiter', 'enleve', 'livre', 'rendu'] as const;
export type StatutConteneur = (typeof STATUTS_CONTENEUR)[number];

export const TYPES_CONTENEUR = ['20', '40', '40hc', 'reefer'] as const;
export type TypeConteneur = (typeof TYPES_CONTENEUR)[number];

export const SOURCES_NUMERO_CONTENEUR = ['manuel', 'import_bol', 'scan', 'ia'] as const;
export type SourceNumeroConteneur = (typeof SOURCES_NUMERO_CONTENEUR)[number];

export const TYPES_DOCUMENT = [
  'bl',
  'facture_charges',
  'do',
  'declaration_douane',
  'bon_livraison',
  'autre',
] as const;
export type TypeDocument = (typeof TYPES_DOCUMENT)[number];

export const ORIGINES_DOCUMENT = ['upload_web', 'photo_mobile', 'whatsapp', 'scan'] as const;
export type OrigineDocument = (typeof ORIGINES_DOCUMENT)[number];

export const STATUTS_INGESTION = ['none', 'en_file', 'extrait', 'valide'] as const;
export type StatutIngestion = (typeof STATUTS_INGESTION)[number];

export const TYPES_FRANCHISE = ['surestaries', 'detention'] as const;
export type TypeFranchise = (typeof TYPES_FRANCHISE)[number];
