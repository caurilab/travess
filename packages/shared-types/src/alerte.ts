import type { StatutAlerte, TypeAlerte } from './enums.js';

/**
 * Alerte (miroir de AlerteResource). Montant menaçant entier (XOF).
 */
export interface AlerteDTO {
  readonly id: string;
  readonly dossier_id: string;
  readonly conteneur_id: string | null;
  readonly type: TypeAlerte;
  readonly montant_menacant: number | null;
  readonly statut: StatutAlerte;
  readonly canaux_envoyes: readonly string[];
  readonly created_at: string | null;
}
