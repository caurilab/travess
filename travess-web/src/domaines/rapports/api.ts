import type { StatistiquesDossiersDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Agrégats d'activité des dossiers (par statut / sens). */
export function chargerStatistiquesDossiers(signal?: AbortSignal): Promise<StatistiquesDossiersDTO> {
  return api.get<StatistiquesDossiersDTO>('/dossiers/statistiques', signal);
}
