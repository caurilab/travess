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
  // Marque (corail-rouge). Recalibrée sur la dominante des inspirations
  // (_reference_design) : rouge chaud, distinct du rouge pur du danger.
  marque: {
    50: '#FFF4F1',
    100: '#FFE3DC',
    200: '#FFC4B7',
    300: '#FB9C88',
    400: '#F4785E',
    500: '#EE5A44', // corail-rouge principal
    600: '#D6412C',
    700: '#B03222',
    800: '#86271B',
    900: '#571913',
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
