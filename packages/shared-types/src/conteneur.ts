import type {
  SourceNumeroConteneur,
  StatutConteneur,
  TypeConteneur,
} from './enums.js';

/**
 * Un jalon daté du parcours d'un conteneur (frise « à la MSC »).
 */
export interface JalonSuiviDTO {
  readonly code: 'depart' | 'position' | 'escale' | 'destination';
  readonly libelle: string;
  readonly lieu: string;
  readonly terminal: string | null;
  /** ISO 8601, ou null si la date n'est pas connue. */
  readonly date: string | null;
  /** true = ETA prévue ; false = mouvement constaté. */
  readonly date_estimee: boolean;
  readonly navire?: string | null;
  readonly detail?: string | null;
  readonly etat: 'fait' | 'actuel' | 'prevu';
}

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
  /** Frise datée du parcours (origine → position → destination). */
  readonly jalons: readonly JalonSuiviDTO[];
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
