/**
 * Calcul des surestaries / détention (port TypeScript).
 *
 * Barème PROGRESSIF : chaque jour facturé est compté au tarif du palier où il
 * tombe ; le total est la somme des tarifs journaliers. Jours CALENDAIRES.
 * `date_debut` = jour 1 de franchise ; `date_fin_franchise` = dernier jour
 * gratuit ; jour entamé = jour dû. Montants ENTIERS (XOF, sans sous-unité).
 *
 * ⚠️ Cœur métier dupliqué par nécessité entre runtimes (TS clients + PHP API).
 * Le port PHP (app/Domains/Surestaries/Support/CalculFranchise) doit rester
 * équivalent ; la parité est garantie par packages/test-vectors/surestaries.json,
 * consommé par les deux batteries de tests. Toute divergence casse la CI.
 */

export interface PalierBareme {
  readonly de_jour: number;
  readonly a_jour: number | null;
  readonly tarif_jour: number;
}

export interface Bareme {
  readonly devise: string;
  readonly paliers_par_type: Record<string, readonly PalierBareme[]>;
}

export type TypeFranchise = 'surestaries' | 'detention';

export interface EntreeFranchise {
  readonly type: TypeFranchise;
  readonly date_debut: string;
  readonly jours_francs: number;
  readonly bareme: Bareme;
  readonly type_conteneur: string;
  readonly statut_conteneur: string;
  readonly date_evaluation: string;
  readonly horizon_menacant_jours: number;
}

export interface ResultatFranchise {
  readonly date_fin_franchise: string;
  readonly premier_jour_facture: string;
  readonly montant_en_cours: number;
  readonly montant_menacant: number;
  readonly actif: boolean;
  readonly jours_factures: number;
}

const MS_PAR_JOUR = 86_400_000;

/** Indice de jour (jours depuis l'époque, en UTC — insensible au fuseau). */
function indiceJour(date: string): number {
  const [annee, mois, jour] = date.split('-').map(Number);
  return Math.floor(Date.UTC(annee!, mois! - 1, jour!) / MS_PAR_JOUR);
}

function versDate(indice: number): string {
  return new Date(indice * MS_PAR_JOUR).toISOString().slice(0, 10);
}

/** Résout les paliers d'un type de conteneur, avec repli sur « defaut ». */
export function resoudrePaliers(bareme: Bareme, typeConteneur: string): readonly PalierBareme[] {
  return bareme.paliers_par_type[typeConteneur] ?? bareme.paliers_par_type['defaut'] ?? [];
}

/** Tarif du k-ième jour facturé (1-indexé) selon les paliers. */
function tarifJour(paliers: readonly PalierBareme[], k: number): number {
  for (const palier of paliers) {
    if (k >= palier.de_jour && (palier.a_jour === null || k <= palier.a_jour)) {
      return palier.tarif_jour;
    }
  }
  return 0;
}

/** Une franchise est-elle active, selon son type et le statut du conteneur ? */
export function estActif(type: TypeFranchise, statutConteneur: string): boolean {
  if (statutConteneur === 'rendu') {
    return false;
  }
  if (type === 'surestaries') {
    return statutConteneur === 'a_traiter';
  }
  return statutConteneur === 'enleve' || statutConteneur === 'livre';
}

export function calculerFranchise(entree: EntreeFranchise): ResultatFranchise {
  const paliers = resoudrePaliers(entree.bareme, entree.type_conteneur);

  const debut = indiceJour(entree.date_debut);
  const finFranchise = debut + entree.jours_francs - 1;
  const premierFacture = finFranchise + 1;
  const evaluation = indiceJour(entree.date_evaluation);

  let montantEnCours = 0;
  let joursFactures = 0;
  for (let d = premierFacture; d <= evaluation; d++) {
    montantEnCours += tarifJour(paliers, d - premierFacture + 1);
    joursFactures++;
  }

  let montantMenacant = 0;
  const debutHorizon = Math.max(evaluation + 1, premierFacture);
  for (let d = debutHorizon; d <= evaluation + entree.horizon_menacant_jours; d++) {
    montantMenacant += tarifJour(paliers, d - premierFacture + 1);
  }

  return {
    date_fin_franchise: versDate(finFranchise),
    premier_jour_facture: versDate(premierFacture),
    montant_en_cours: montantEnCours,
    montant_menacant: montantMenacant,
    actif: estActif(entree.type, entree.statut_conteneur),
    jours_factures: joursFactures,
  };
}
