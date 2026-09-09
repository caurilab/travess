import { describe, expect, it } from 'vitest';

import vecteurs from '../../../test-vectors/surestaries.json' with { type: 'json' };
import { calculerFranchise, type Bareme, type TypeFranchise } from './calculer.js';

const baremes = vecteurs.baremes as Record<string, Bareme>;

describe('Surestaries / détention — calcul (vecteurs partagés)', () => {
  it.each(vecteurs.cas)('$nom', ({ entree, attendu }) => {
    const resultat = calculerFranchise({
      type: entree.type as TypeFranchise,
      date_debut: entree.date_debut,
      jours_francs: entree.jours_francs,
      bareme: baremes[entree.bareme_ref]!,
      type_conteneur: entree.type_conteneur,
      statut_conteneur: entree.statut_conteneur,
      date_evaluation: entree.date_evaluation,
      horizon_menacant_jours: entree.horizon_menacant_jours,
    });

    expect(resultat).toEqual(attendu);
  });
});
