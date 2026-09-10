import type { DossierPortailDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Dossiers partagés au client connecté (vue limitée). */
export function listerMesDossiers(signal?: AbortSignal): Promise<readonly DossierPortailDTO[]> {
  return api.get<readonly DossierPortailDTO[]>('/portail/dossiers', signal);
}

export function chargerMonDossier(id: string, signal?: AbortSignal): Promise<DossierPortailDTO> {
  return api.get<DossierPortailDTO>(`/portail/dossiers/${id}`, signal);
}

/** Réponse d'émission d'invitation (surface agent). */
export interface EmissionInvitation {
  readonly invitation: { readonly id: string; readonly statut: string; readonly canal: string; readonly expire_at: string };
  readonly lien: string;
}

/** Émet une invitation portail pour un dossier (transitaire → client). */
export function emettreInvitation(
  dossierId: string,
  donnees: { readonly canal: 'email' | 'whatsapp'; readonly destinataire: string },
): Promise<EmissionInvitation> {
  return api.post<EmissionInvitation>(`/dossiers/${dossierId}/invitations`, donnees);
}

/**
 * Transforme le lien signé de l'API (…/api/v1/portail/invitations/{token}?…) en
 * lien front partageable (…/invitation/{token}?…). La signature voyage dans la
 * query et reste valable : elle est vérifiée quand la page rejoue l'URL de l'API.
 */
export function lienFrontInvitation(lienApi: string): string {
  try {
    const url = new URL(lienApi);
    const token = url.pathname.split('/').pop() ?? '';
    return `${window.location.origin}/invitation/${token}${url.search}`;
  } catch {
    return lienApi;
  }
}
