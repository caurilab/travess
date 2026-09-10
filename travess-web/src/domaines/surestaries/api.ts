import type { ArgentEnFeuDTO, SurestariesEviteesDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

export function chargerArgentEnFeu(signal?: AbortSignal): Promise<ArgentEnFeuDTO> {
  return api.get<ArgentEnFeuDTO>('/dashboard/argent-en-feu', signal);
}

export function chargerSurestariesEvitees(signal?: AbortSignal): Promise<SurestariesEviteesDTO> {
  return api.get<SurestariesEviteesDTO>('/dashboard/surestaries-evitees', signal);
}
