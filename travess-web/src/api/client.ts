import type { Enveloppe, ErreurApi } from '@travess/shared-types';

import { effacerJeton, lireJeton } from './session.js';

/**
 * Client HTTP typé de l'API Travess (contrat docs/10).
 *
 * - préfixe /api/v1 (proxifié vers Laravel en dev, cf. vite.config) ;
 * - jeton Bearer attaché automatiquement ;
 * - déballe l'enveloppe { data } ; lève ErreurRequete sur statut non 2xx ;
 * - le tenant vient du jeton, jamais du client (principe n°3).
 *
 * Base : relative « /api/v1 » sur le web (proxy Vite en dev, reverse-proxy en
 * prod). Le desktop Electron sert le bundle sans proxy : sa couche native
 * injecte `window.travessDesktop.apiBase` (origine absolue de l'API) — le même
 * bundle est réutilisé sans modification (principe n°8).
 */
declare global {
  interface Window {
    readonly travessDesktop?: { readonly apiBase?: string };
  }
}

const ORIGINE_API =
  typeof window !== 'undefined' && typeof window.travessDesktop?.apiBase === 'string'
    ? window.travessDesktop.apiBase.replace(/\/+$/, '')
    : '';
const BASE = `${ORIGINE_API}/api/v1`;

export class ErreurRequete extends Error {
  constructor(
    public readonly statut: number,
    public readonly code: string,
    message: string,
    public readonly details?: Record<string, unknown>,
  ) {
    super(message);
    this.name = 'ErreurRequete';
  }
}

interface Options {
  readonly methode?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  readonly corps?: unknown;
  readonly signal?: AbortSignal;
}

async function requete<T>(chemin: string, options: Options = {}): Promise<T> {
  const jeton = lireJeton();

  const reponse = await fetch(BASE + chemin, {
    method: options.methode ?? 'GET',
    headers: {
      Accept: 'application/json',
      ...(options.corps !== undefined ? { 'Content-Type': 'application/json' } : {}),
      ...(jeton !== null ? { Authorization: `Bearer ${jeton}` } : {}),
    },
    body: options.corps !== undefined ? JSON.stringify(options.corps) : undefined,
    signal: options.signal,
  });

  if (reponse.status === 401) {
    // Jeton expiré/révoqué : on nettoie la session (le routeur redirigera).
    effacerJeton();
  }

  if (reponse.status === 204) {
    return undefined as T;
  }

  const charge: unknown = await reponse.json().catch(() => null);

  if (!reponse.ok) {
    const erreur = (charge as ErreurApi | null)?.error;
    throw new ErreurRequete(
      reponse.status,
      erreur?.code ?? 'erreur',
      erreur?.message ?? 'Une erreur est survenue.',
      erreur?.details,
    );
  }

  // La plupart des réponses sont enveloppées { data } ; certaines (auth) sont plates.
  const enveloppe = charge as Enveloppe<T> | T;
  if (enveloppe !== null && typeof enveloppe === 'object' && 'data' in enveloppe) {
    return (enveloppe as Enveloppe<T>).data;
  }
  return enveloppe as T;
}

/** Envoi multipart (téléversement de fichier) — pas de Content-Type imposé. */
async function televerser<T>(chemin: string, donnees: FormData): Promise<T> {
  const jeton = lireJeton();
  const reponse = await fetch(BASE + chemin, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(jeton !== null ? { Authorization: `Bearer ${jeton}` } : {}),
    },
    body: donnees,
  });

  const charge: unknown = await reponse.json().catch(() => null);
  if (!reponse.ok) {
    const erreur = (charge as ErreurApi | null)?.error;
    throw new ErreurRequete(reponse.status, erreur?.code ?? 'erreur', erreur?.message ?? 'Téléversement impossible.', erreur?.details);
  }
  const enveloppe = charge as Enveloppe<T> | T;
  if (enveloppe !== null && typeof enveloppe === 'object' && 'data' in enveloppe) {
    return (enveloppe as Enveloppe<T>).data;
  }
  return enveloppe as T;
}

export const api = {
  get: <T>(chemin: string, signal?: AbortSignal) => requete<T>(chemin, { signal }),
  post: <T>(chemin: string, corps?: unknown) => requete<T>(chemin, { methode: 'POST', corps }),
  put: <T>(chemin: string, corps?: unknown) => requete<T>(chemin, { methode: 'PUT', corps }),
  patch: <T>(chemin: string, corps?: unknown) => requete<T>(chemin, { methode: 'PATCH', corps }),
  supprimer: <T>(chemin: string) => requete<T>(chemin, { methode: 'DELETE' }),
  televerser,
};
