/**
 * Stockage du jeton de session (Sanctum Bearer).
 *
 * Le jeton vit dans localStorage : suffisant pour l'app agent (desktop réutilise
 * le web). À durcir si l'on adopte des cookies httpOnly côté portail public.
 */
const CLE = 'travess.jeton';

export function lireJeton(): string | null {
  try {
    return localStorage.getItem(CLE);
  } catch {
    return null;
  }
}

export function ecrireJeton(jeton: string): void {
  try {
    localStorage.setItem(CLE, jeton);
  } catch {
    /* stockage indisponible : la session ne survivra pas au rechargement */
  }
}

export function effacerJeton(): void {
  try {
    localStorage.removeItem(CLE);
  } catch {
    /* rien à faire */
  }
}
