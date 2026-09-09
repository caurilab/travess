import { describe, expect, it } from 'vitest';

import vecteurs from '../../../test-vectors/iso6346.json' with { type: 'json' };
import {
  chiffreDeControle,
  estNumeroConteneurValide,
  normaliserNumeroConteneur,
} from './valider.js';

describe('ISO 6346 — validation', () => {
  it.each(vecteurs.valides)('accepte le numéro valide %s', (numero) => {
    expect(estNumeroConteneurValide(numero)).toBe(true);
  });

  it.each(vecteurs.invalides)('rejette « $numero » ($raison)', ({ numero }) => {
    expect(estNumeroConteneurValide(numero)).toBe(false);
  });
});

describe('ISO 6346 — chiffre de contrôle', () => {
  it.each(vecteurs.chiffres_de_controle)(
    'préfixe $prefixe → $attendu',
    ({ prefixe, attendu }) => {
      expect(chiffreDeControle(prefixe)).toBe(attendu);
    },
  );
});

describe('ISO 6346 — normalisation', () => {
  it('met en majuscules et retire les espaces', () => {
    expect(normaliserNumeroConteneur('csqu 3054383')).toBe('CSQU3054383');
    expect(estNumeroConteneurValide(normaliserNumeroConteneur('csqu 3054383'))).toBe(true);
  });
});
