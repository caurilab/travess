import { couleurs } from '../tokens/couleurs.js';

/**
 * Jeu de statuts normalisé : un même vocabulaire de « tons » réutilisé partout
 * (conteneur, étape, paiement, dossier). Les valeurs de statut métier
 * (à_traiter, livré, réglé…) seront rattachées à un ton au fil des lots ; le
 * ton pilote la présentation (couleur du badge).
 */
export type TonStatut = 'risque' | 'encours' | 'regle' | 'neutre' | 'info';

export interface AspectStatut {
  /** Couleur du texte / de la bordure du badge. */
  readonly couleur: string;
  /** Fond teinté du badge. */
  readonly fond: string;
}

const ASPECTS: Record<TonStatut, AspectStatut> = {
  risque: { couleur: couleurs.risque, fond: '#FEE4E2' },
  encours: { couleur: '#B54708', fond: '#FEF0C7' },
  regle: { couleur: '#027A48', fond: '#D1FADF' },
  info: { couleur: '#175CD3', fond: '#D1E9FF' },
  neutre: { couleur: couleurs.neutre[600], fond: couleurs.neutre[100] },
};

export function aspectPourTon(ton: TonStatut): AspectStatut {
  return ASPECTS[ton];
}
