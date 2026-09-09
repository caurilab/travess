/**
 * Jetons de couleur Travess.
 *
 * Direction validée : marque corail (chaleur logistique), avec une couleur
 * d'URGENCE distincte (rouge vif) réservée au risque — surestaries « argent
 * qui brûle ». Sémantique de statut : à risque (rouge) / en cours (ambre) /
 * réglé (vert). Base claire, texte quasi-noir, surfaces blanches.
 *
 * Valeurs de travail : elles pourront être recalibrées après revue fine des
 * inspirations (_reference_design). Les rôles, eux, sont stables.
 */
export const couleurs = {
  // Marque (corail).
  marque: {
    50: '#FFF3EF',
    100: '#FFE1D8',
    200: '#FFC2B0',
    300: '#FF9C82',
    400: '#F87556',
    500: '#EA5A3D', // corail principal
    600: '#C9452B',
    700: '#A13521',
    800: '#78271A',
    900: '#4F1A11',
  },

  // Sémantique de statut.
  risque: '#D92D20', // à risque / danger / surestaries menaçantes
  encours: '#F59E0B', // en cours / en attente
  regle: '#12B76A', // réglé / fait / succès
  info: '#2E90FA', // information / neutre actionnable

  // Neutres.
  neutre: {
    0: '#FFFFFF',
    50: '#FAFAF9',
    100: '#F5F5F4',
    200: '#E7E5E4',
    300: '#D6D3D1',
    400: '#A8A29E',
    500: '#78716C',
    600: '#57534E',
    700: '#44403C',
    800: '#292524',
    900: '#1C1917',
  },
} as const;

/**
 * Rôles sémantiques (référence : ce que les composants consomment, jamais une
 * valeur hexadécimale en dur).
 */
export const roles = {
  fond: couleurs.neutre[50],
  surface: couleurs.neutre[0],
  texte: couleurs.neutre[900],
  texteAttenue: couleurs.neutre[500],
  bordure: couleurs.neutre[200],
  primaire: couleurs.marque[500],
  primaireContraste: couleurs.neutre[0],
  risque: couleurs.risque,
  encours: couleurs.encours,
  regle: couleurs.regle,
  info: couleurs.info,
} as const;
