import { api } from '../../api/client.js';

/** Visibilité du transitaire dans l'annuaire de la plateforme (opt-in, gérant). */
export function definirVisibiliteAnnuaire(visible: boolean): Promise<{ readonly annuaire_public: boolean }> {
  return api.put('/agence/annuaire', { public: visible });
}
