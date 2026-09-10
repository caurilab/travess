import type {
  SourceNumeroConteneur,
  StatutConteneur,
  TypeConteneur,
} from './enums.js';

/**
 * Dernier suivi tracking d'un conteneur (projeté sur la fiche agent).
 */
export interface DernierSuiviDTO {
  readonly source: 'jsoncargo' | 'imap' | 'manuel';
  readonly statut_brut: string | null;
  readonly emplacement: string | null;
  readonly eta_destination: string | null;
  readonly navire_nom: string | null;
  readonly navire_imo: string | null;
  readonly prochain_poll_prevu: string | null;
  readonly capture_le: string | null;
}

/**
 * Conteneur (miroir de ConteneurResource).
 */
export interface ConteneurDTO {
  readonly id: string;
  readonly bl_id: string;
  readonly numero: string;
  readonly type: TypeConteneur;
  readonly statut: StatutConteneur;
  readonly source_numero: SourceNumeroConteneur;
  /** Présent sur le détail dossier (relation suivis chargée) ; null si jamais suivi. */
  readonly dernier_suivi?: DernierSuiviDTO | null;
}

/**
 * Connaissement (miroir de BlResource). Les conteneurs ne sont présents que
 * sur le détail (whenLoaded côté API).
 */
export interface BlDTO {
  readonly id: string;
  readonly dossier_id: string;
  readonly numero: string;
  readonly armateur_id: string;
  readonly navire_nom: string | null;
  readonly navire_imo: string | null;
  readonly conteneurs?: readonly ConteneurDTO[];
}
