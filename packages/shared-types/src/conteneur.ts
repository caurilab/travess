import type {
  SourceNumeroConteneur,
  StatutConteneur,
  TypeConteneur,
} from './enums.js';

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
