import type { BrouillonMessageDTO, CreerMessagePayload, MessageDTO, TypeDemande } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Fil de correspondance d'un dossier (trié -created_at côté API). */
export function listerMessages(dossierId: string, signal?: AbortSignal): Promise<readonly MessageDTO[]> {
  return api.get<readonly MessageDTO[]>(`/dossiers/${dossierId}/messages`, signal);
}

/** Génère un brouillon pré-rempli (non persisté) pour un type de demande. */
export function genererBrouillon(dossierId: string, typeDemande: TypeDemande): Promise<BrouillonMessageDTO> {
  return api.post<BrouillonMessageDTO>(`/dossiers/${dossierId}/messages/brouillon`, { type_demande: typeDemande });
}

/** Crée et met en file l'envoi d'une demande à l'armateur (202). */
export function envoyerMessage(dossierId: string, payload: CreerMessagePayload): Promise<MessageDTO> {
  return api.post<MessageDTO>(`/dossiers/${dossierId}/messages`, payload);
}
