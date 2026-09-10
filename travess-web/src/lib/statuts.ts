import type { TonStatut } from '@travess/ui';
import type { StatutConteneur, StatutDossier, StatutEtape } from '@travess/shared-types';

/** Mappe les statuts métier vers un ton + un libellé lisible. */

const DOSSIER: Record<StatutDossier, { ton: TonStatut; libelle: string }> = {
  ouvert: { ton: 'info', libelle: 'Ouvert' },
  en_cours: { ton: 'encours', libelle: 'En cours' },
  bloque: { ton: 'risque', libelle: 'Bloqué' },
  cloture: { ton: 'regle', libelle: 'Clôturé' },
};

const ETAPE: Record<StatutEtape, { ton: TonStatut; libelle: string }> = {
  a_faire: { ton: 'neutre', libelle: 'À faire' },
  en_cours: { ton: 'encours', libelle: 'En cours' },
  fait: { ton: 'regle', libelle: 'Fait' },
  en_retard: { ton: 'risque', libelle: 'En retard' },
};

const CONTENEUR: Record<StatutConteneur, { ton: TonStatut; libelle: string }> = {
  a_traiter: { ton: 'neutre', libelle: 'À traiter' },
  enleve: { ton: 'encours', libelle: 'Enlevé' },
  livre: { ton: 'regle', libelle: 'Livré' },
  rendu: { ton: 'info', libelle: 'Rendu' },
};

export const statutDossier = (s: StatutDossier) => DOSSIER[s];
export const statutEtape = (s: StatutEtape) => ETAPE[s];
export const statutConteneur = (s: StatutConteneur) => CONTENEUR[s];

export function libelleSens(sens: 'import' | 'export'): string {
  return sens === 'import' ? 'Import' : 'Export';
}
