import type { DocumentDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Résultat d'extraction (miroir d'ExtractionResource). */
export interface ChampExtraitDTO {
  readonly valeur: unknown;
  readonly confiance: number;
  readonly zone_source: string | null;
}
export interface ExtractionDTO {
  readonly id: string;
  readonly document_id: string;
  readonly statut: 'en_file' | 'reussi' | 'echoue';
  readonly champs: Record<string, ChampExtraitDTO>;
  readonly corrections: Record<string, unknown>;
  readonly valide_at: string | null;
}

export function deposerDocument(dossierId: string, type: string, fichier: File): Promise<DocumentDTO> {
  const donnees = new FormData();
  donnees.set('dossier_id', dossierId);
  donnees.set('type', type);
  donnees.set('fichier', fichier);
  return api.televerser<DocumentDTO>('/documents', donnees);
}

export function lancerExtraction(documentId: string): Promise<ExtractionDTO> {
  return api.post<ExtractionDTO>(`/documents/${documentId}/extraction`);
}

export function chargerExtraction(documentId: string, signal?: AbortSignal): Promise<ExtractionDTO> {
  return api.get<ExtractionDTO>(`/documents/${documentId}/extraction`, signal);
}

export function validerExtraction(extractionId: string, corrections: Record<string, unknown>): Promise<unknown> {
  return api.post(`/extractions/${extractionId}/validation`, { corrections });
}
