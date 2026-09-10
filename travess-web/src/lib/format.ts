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

const MS_PAR_JOUR = 86_400_000;

/**
 * Jours (calendaires, UTC) entre aujourd'hui et une date ISO butoir.
 * > 0 = à venir, 0 = aujourd'hui, < 0 = échue. null si la date est absente.
 */
export function joursRestants(iso: string | null, aujourdhui: Date = new Date()): number | null {
  if (iso === null) return null;
  const [a, m, j] = iso.split('-').map(Number);
  if (j === undefined) return null;
  const cible = Math.floor(Date.UTC(a!, m! - 1, j) / MS_PAR_JOUR);
  const socle = Math.floor(
    Date.UTC(aujourdhui.getFullYear(), aujourdhui.getMonth(), aujourdhui.getDate()) / MS_PAR_JOUR,
  );
  return cible - socle;
}

/** Libellé humain d'un compte à rebours : « Dans 3 j », « Aujourd'hui », « En retard de 2 j ». */
export function libelleEcheance(jours: number | null): string {
  if (jours === null) return '—';
  if (jours === 0) return "Aujourd'hui";
  if (jours > 0) return `Dans ${jours} j`;
  return `En retard de ${Math.abs(jours)} j`;
}
