/** Formatage FCFA (XOF) : entiers, séparateur d'espace (fr). */
const FCFA = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });

export function montant(valeur: number): string {
  return `${FCFA.format(valeur)} FCFA`;
}

/** Date ISO (yyyy-mm-dd) → jj/mm/aaaa ; null → tiret. */
export function dateCourte(iso: string | null): string {
  if (iso === null) return '—';
  const [a, m, j] = iso.split('-');
  return j !== undefined ? `${j}/${m}/${a}` : iso;
}
