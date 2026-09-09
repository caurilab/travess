import type { ClientDTO } from './client.js';
import type { BlDTO } from './conteneur.js';
import type { DocumentDTO } from './document.js';
import type { SensDossier, StatutDossier, StatutEtape } from './enums.js';
import type { UtilisateurDTO } from './auth.js';

/**
 * Étape de workflow d'un dossier (miroir de EtapeResource).
 */
export interface EtapeDTO {
  readonly id: string;
  readonly ordre: number;
  readonly libelle: string;
  readonly sla_jours: number;
  readonly date_prevue: string | null;
  readonly date_reelle: string | null;
  readonly statut: StatutEtape;
  readonly responsable_id: string | null;
}

/**
 * Résumé financier d'un dossier (forme figée ; alimentée au lot 3).
 */
export interface FinancesResumeDTO {
  readonly charges: number;
  readonly encaissements: number;
  readonly honoraires: number;
  readonly solde: number;
}

/**
 * Dossier (miroir de DossierResource). Les relations et résumés ne sont
 * présents que sur le détail (whenLoaded côté API).
 */
export interface DossierDTO {
  readonly id: string;
  readonly reference: string;
  readonly sens: SensDossier;
  readonly statut: StatutDossier;
  readonly motif_blocage: string | null;
  readonly client_id: string;
  readonly created_at: string | null;
  readonly client?: ClientDTO;
  readonly etapes?: readonly EtapeDTO[];
  readonly agents?: readonly UtilisateurDTO[];
  readonly bls?: readonly BlDTO[];
  readonly documents?: readonly DocumentDTO[];
  readonly finances?: FinancesResumeDTO;
  readonly transport?: null;
}

/**
 * Corps de POST /dossiers.
 */
export interface CreerDossierPayload {
  readonly sens: SensDossier;
  readonly client_id: string;
}
