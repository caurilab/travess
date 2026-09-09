import type { TypeFranchise } from './enums.js';

/**
 * Palier d'un barème : de_jour..a_jour (a_jour null = palier ouvert) → tarif/jour.
 */
export interface PalierBaremeDTO {
  readonly de_jour: number;
  readonly a_jour: number | null;
  readonly tarif_jour: number;
}

/**
 * Barème de surestaries/détention (paliers par type de conteneur, devise XOF).
 */
export interface BaremeDTO {
  readonly devise: string;
  readonly paliers_par_type: Record<string, readonly PalierBaremeDTO[]>;
}

/**
 * Franchise (miroir de FranchiseResource). Les champs date_fin_franchise,
 * montant_en_cours, montant_menacant et actif sont calculés (jamais saisis).
 * Montants entiers (XOF).
 */
export interface FranchiseDTO {
  readonly id: string;
  readonly conteneur_id: string;
  readonly type: TypeFranchise;
  readonly date_debut: string;
  readonly jours_francs: number;
  readonly date_fin_franchise: string | null;
  readonly montant_en_cours: number;
  readonly montant_menacant: number;
  readonly actif: boolean;
}
