import type { DemandeAssignationDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Demandes d'assignation en attente visant le tenant courant (transitaire). */
export function listerDemandesAssignation(signal?: AbortSignal): Promise<readonly DemandeAssignationDTO[]> {
  return api.get<readonly DemandeAssignationDTO[]>('/demandes-assignation', signal);
}

/** Accepte une demande : prend la propriété du dossier (migration). */
export function accepterAssignation(id: string): Promise<{ readonly ancienne_reference?: string; readonly nouvelle_reference?: string }> {
  return api.post(`/demandes-assignation/${id}/accepter`);
}

export function refuserAssignation(id: string, motif: string): Promise<DemandeAssignationDTO> {
  return api.post<DemandeAssignationDTO>(`/demandes-assignation/${id}/refuser`, { motif });
}
