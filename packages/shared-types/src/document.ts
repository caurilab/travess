import type { OrigineDocument, StatutIngestion, TypeDocument } from './enums.js';

/**
 * Document (miroir de DocumentResource).
 */
export interface DocumentDTO {
  readonly id: string;
  readonly dossier_id: string;
  readonly type: TypeDocument;
  readonly origine: OrigineDocument;
  readonly statut_ingestion: StatutIngestion;
  readonly nom_original: string | null;
  readonly mime: string | null;
  readonly taille: number | null;
  readonly created_at: string | null;
}
