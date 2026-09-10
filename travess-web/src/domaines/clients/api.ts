import type { ClientDTO, CreerClientPayload } from '@travess/shared-types';

import { api } from '../../api/client.js';

export function listerClients(recherche: string, signal?: AbortSignal): Promise<readonly ClientDTO[]> {
  const suffixe = recherche.trim() === '' ? '' : `?filter[nom]=${encodeURIComponent(recherche.trim())}`;
  return api.get<readonly ClientDTO[]>(`/clients${suffixe}`, signal);
}

export function creerClient(payload: CreerClientPayload): Promise<ClientDTO> {
  return api.post<ClientDTO>('/clients', payload);
}

export function mettreAJourClient(id: string, payload: Partial<CreerClientPayload>): Promise<ClientDTO> {
  return api.patch<ClientDTO>(`/clients/${id}`, payload);
}
