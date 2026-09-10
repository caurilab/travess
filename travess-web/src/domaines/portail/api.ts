import type { DossierPortailDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Dossiers partagés au client connecté (vue limitée). */
export function listerMesDossiers(signal?: AbortSignal): Promise<readonly DossierPortailDTO[]> {
  return api.get<readonly DossierPortailDTO[]>('/portail/dossiers', signal);
}

export function chargerMonDossier(id: string, signal?: AbortSignal): Promise<DossierPortailDTO> {
  return api.get<DossierPortailDTO>(`/portail/dossiers/${id}`, signal);
}
