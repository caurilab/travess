import { ErreurRequete } from '../../api/client.js';

/**
 * Onboarding public (ADR-013) : appels NON authentifiés (le compte n'existe pas
 * encore). La sécurité repose sur l'URL signée (montrer) et le token + OTP.
 * On n'attache jamais de jeton Bearer ici.
 */
const BASE = '/api/v1';

export interface InvitationApercu {
  readonly canal: string;
  readonly destination_masquee: string;
  readonly otp_expire_at: string;
}

async function lire<T>(reponse: Response): Promise<T> {
  const charge: unknown = await reponse.json().catch(() => null);
  if (!reponse.ok) {
    const err = (charge as { error?: { code?: string; message?: string } } | null)?.error;
    // Les endpoints d'onboarding renvoient parfois { message } (HttpException) plutôt que l'enveloppe.
    const message = err?.message ?? (charge as { message?: string } | null)?.message ?? 'Requête impossible.';
    throw new ErreurRequete(reponse.status, err?.code ?? 'erreur', message);
  }
  return charge as T;
}

/**
 * Réclame l'invitation (déclenche l'envoi de l'OTP). `requete` est la
 * query-string signée (expires + signature) transmise par le lien.
 */
export async function reclamerInvitation(token: string, requete: string): Promise<InvitationApercu> {
  const suffixe = requete === '' ? '' : `?${requete}`;
  const reponse = await fetch(`${BASE}/portail/invitations/${token}${suffixe}`, {
    headers: { Accept: 'application/json' },
  });
  return lire<InvitationApercu>(reponse);
}

export async function confirmerInvitation(
  token: string,
  donnees: { readonly code_otp: string; readonly mot_de_passe?: string; readonly nom?: string },
): Promise<{ readonly token: string; readonly user_id: string }> {
  const reponse = await fetch(`${BASE}/portail/invitations/${token}/confirmer`, {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(donnees),
  });
  return lire<{ token: string; user_id: string }>(reponse);
}
