import type { ConteneurDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

export function creerConteneur(blId: string, numero: string, type: string): Promise<ConteneurDTO> {
  return api.post<ConteneurDTO>('/conteneurs', { bl_id: blId, numero, type });
}

export function mettreAJourConteneur(id: string, statut: string): Promise<ConteneurDTO> {
  return api.patch<ConteneurDTO>(`/conteneurs/${id}`, { statut });
}
