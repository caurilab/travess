import type {
  BlPortailDTO,
  ConteneurPortailDTO,
  DemandeAssignationDTO,
  DossierAutonomeDTO,
  DossierPortailDTO,
  SensDossier,
  TransitaireAnnuaireDTO,
} from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Dossiers partagés au client connecté (vue limitée). */
export function listerMesDossiers(signal?: AbortSignal): Promise<readonly DossierPortailDTO[]> {
  return api.get<readonly DossierPortailDTO[]>('/portail/dossiers', signal);
}

// --- Surface autonome : le client possède et gère ses propres dossiers ---

export function listerMesDossiersAutonomes(signal?: AbortSignal): Promise<readonly DossierAutonomeDTO[]> {
  return api.get<readonly DossierAutonomeDTO[]>('/portail/autonome/dossiers', signal);
}

export function chargerMonDossierAutonome(id: string, signal?: AbortSignal): Promise<DossierAutonomeDTO> {
  return api.get<DossierAutonomeDTO>(`/portail/autonome/dossiers/${id}`, signal);
}

export function creerDossierAutonome(sens: SensDossier): Promise<DossierAutonomeDTO> {
  return api.post<DossierAutonomeDTO>('/portail/autonome/dossiers', { sens });
}

export function ajouterBlAutonome(
  dossierId: string,
  donnees: { readonly numero: string; readonly armateur: string; readonly navire_nom?: string; readonly navire_imo?: string },
): Promise<BlPortailDTO> {
  return api.post<BlPortailDTO>(`/portail/autonome/dossiers/${dossierId}/bls`, donnees);
}

export function ajouterConteneurAutonome(
  blId: string,
  donnees: { readonly numero: string; readonly type: string },
): Promise<ConteneurPortailDTO> {
  return api.post<ConteneurPortailDTO>(`/portail/autonome/bls/${blId}/conteneurs`, donnees);
}

export function chargerAnnuaire(recherche: string, signal?: AbortSignal): Promise<readonly TransitaireAnnuaireDTO[]> {
  const suffixe = recherche.trim() === '' ? '' : `?q=${encodeURIComponent(recherche.trim())}`;
  return api.get<readonly TransitaireAnnuaireDTO[]>(`/portail/autonome/annuaire${suffixe}`, signal);
}

export function demanderAssignation(
  dossierId: string,
  donnees: { readonly transitaire_id: string; readonly message?: string },
): Promise<DemandeAssignationDTO> {
  return api.post<DemandeAssignationDTO>(`/portail/autonome/dossiers/${dossierId}/assignation`, donnees);
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
