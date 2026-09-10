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

import type { StatutAlerte, TypeAlerte } from '@travess/shared-types';

const ALERTE_STATUT: Record<StatutAlerte, { ton: TonStatut; libelle: string }> = {
  ouverte: { ton: 'risque', libelle: 'Ouverte' },
  vue: { ton: 'encours', libelle: 'Vue' },
  traitee: { ton: 'regle', libelle: 'Traitée' },
};

const ALERTE_TYPE: Record<TypeAlerte, { ton: TonStatut; libelle: string }> = {
  surestaries_j3: { ton: 'encours', libelle: 'Surestaries J-3' },
  surestaries_j1: { ton: 'risque', libelle: 'Surestaries J-1' },
  surestaries_j0: { ton: 'risque', libelle: 'Surestaries J0' },
  detention_j3: { ton: 'encours', libelle: 'Détention J-3' },
  detention_j1: { ton: 'risque', libelle: 'Détention J-1' },
  detention_j0: { ton: 'risque', libelle: 'Détention J0' },
  sla_depasse: { ton: 'risque', libelle: 'SLA dépassé' },
  blocage: { ton: 'risque', libelle: 'Blocage' },
};

export const statutAlerte = (s: StatutAlerte) => ALERTE_STATUT[s];
export const typeAlerte = (t: TypeAlerte) => ALERTE_TYPE[t];
