/**
 * Enveloppes de réponse normalisées de l'API (contrat docs/10 §1).
 */

export interface MetaPagination {
  readonly page?: number;
  readonly per_page?: number;
  readonly total?: number;
  readonly cursor?: string | null;
}

export interface Enveloppe<T> {
  readonly data: T;
  readonly meta?: MetaPagination;
}

export interface ErreurApi {
  readonly error: {
    readonly code: string;
    readonly message: string;
    readonly details?: Record<string, unknown>;
  };
}
