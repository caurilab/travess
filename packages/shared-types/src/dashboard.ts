import type { TypeFranchise } from './enums.js';

/**
 * Ligne « conteneur à risque » du tableau de bord « argent en feu ».
 */
export interface ConteneurARisqueDTO {
  readonly franchise_id: string;
  readonly conteneur_id: string;
  readonly numero: string;
  readonly type: TypeFranchise;
  readonly date_fin_franchise: string | null;
  readonly montant_en_cours: number;
  readonly montant_menacant: number;
}

/**
 * GET /dashboard/argent-en-feu.
 */
export interface ArgentEnFeuDTO {
  readonly menacant_cumule: number;
  readonly en_cours_cumule: number;
  readonly conteneurs_a_risque: readonly ConteneurARisqueDTO[];
}

/**
 * GET /dashboard/surestaries-evitees (métrique de valeur du mois).
 */
export interface SurestariesEviteesDTO {
  readonly mois: string;
  readonly montant_evite: number;
  readonly nombre_conteneurs: number;
}
