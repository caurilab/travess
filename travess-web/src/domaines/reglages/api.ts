import type { Enrolement2faDTO } from '@travess/shared-types';

import { api } from '../../api/client.js';

/** Visibilité du transitaire dans l'annuaire de la plateforme (opt-in, gérant). */
export function definirVisibiliteAnnuaire(visible: boolean): Promise<{ readonly annuaire_public: boolean }> {
  return api.put('/agence/annuaire', { public: visible });
}

/** Démarre l'enrôlement 2FA : renvoie le secret, l'URL otpauth et les codes de récupération. */
export function activer2fa(): Promise<Enrolement2faDTO> {
  return api.post<Enrolement2faDTO>('/auth/2fa/activer');
}

/** Confirme l'activation 2FA avec un code TOTP (204). */
export function confirmer2fa(code: string): Promise<void> {
  return api.post('/auth/2fa/confirmer', { code });
}

/** Désactive le 2FA après vérification d'un code frais (204). */
export function desactiver2fa(code: string): Promise<void> {
  return api.post('/auth/2fa/desactiver', { code });
}
