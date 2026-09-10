import type { DossierDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

export interface FiltresDossiers {
  readonly statut?: string;
  readonly sens?: string;
}

export function listerDossiers(filtres: FiltresDossiers, signal?: AbortSignal): Promise<readonly DossierDTO[]> {
  const params = new URLSearchParams();
  if (filtres.statut) params.set('filter[statut]', filtres.statut);
  if (filtres.sens) params.set('filter[sens]', filtres.sens);
  const suffixe = params.toString() === '' ? '' : `?${params.toString()}`;
  return api.get<readonly DossierDTO[]>(`/dossiers${suffixe}`, signal);
}

export function chargerDossier(id: string, signal?: AbortSignal): Promise<DossierDTO> {
  return api.get<DossierDTO>(`/dossiers/${id}`, signal);
}
