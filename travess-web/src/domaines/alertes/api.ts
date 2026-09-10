import type { AlerteDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

export function listerAlertes(statut: string, signal?: AbortSignal): Promise<readonly AlerteDTO[]> {
  const suffixe = statut === '' ? '' : `?filter[statut]=${statut}`;
  return api.get<readonly AlerteDTO[]>(`/alertes${suffixe}`, signal);
}

export function marquerAlerte(id: string, statut: 'vue' | 'traitee'): Promise<AlerteDTO> {
  return api.patch<AlerteDTO>(`/alertes/${id}`, { statut });
}
