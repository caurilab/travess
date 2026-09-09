/**
 * Validation ISO 6346 du numéro de conteneur (port TypeScript).
 *
 * Forme canonique : 3 lettres (code propriétaire) + 1 lettre de catégorie
 * (U, J ou Z) + 6 chiffres (série) + 1 chiffre de contrôle. Total 11 caractères,
 * en majuscules, sans espace.
 *
 * ⚠️ Cœur métier dupliqué par nécessité entre runtimes (TS clients + PHP API).
 * L'implémentation PHP (travess-api, domaine Conteneurs) doit rester équivalente.
 * La parité est garantie par les vecteurs partagés packages/test-vectors/iso6346.json,
 * consommés par les deux batteries de tests. Toute divergence casse la CI.
 */

const FORME_CANONIQUE = /^[A-Z]{3}[UJZ][0-9]{6}[0-9]$/;

/**
 * Valeur ISO 6346 d'une lettre : A = 10, puis incrément en sautant les
 * multiples de 11 (11, 22, 33).
 */
function valeurLettre(lettre: string): number {
  const code = lettre.charCodeAt(0);
  let n = 10;
  for (let c = 65 /* A */; c <= 90 /* Z */; c++) {
    if (n % 11 === 0) {
      n++;
    }
    if (c === code) {
      return n;
    }
    n++;
  }
  throw new Error(`Lettre hors A-Z : ${lettre}`);
}

/**
 * Valeur d'un caractère de la partie significative (chiffre ou lettre).
 */
function valeurCaractere(caractere: string): number {
  if (caractere >= '0' && caractere <= '9') {
    return caractere.charCodeAt(0) - 48;
  }
  return valeurLettre(caractere);
}

/**
 * Nettoie une saisie utilisateur vers la forme canonique (majuscules, sans espace).
 */
export function normaliserNumeroConteneur(entree: string): string {
  return entree.replace(/\s+/g, '').toUpperCase();
}

/**
 * Calcule le chiffre de contrôle des 10 premiers caractères (préfixe canonique).
 * Un reste de 10 est ramené à 0 (convention ISO 6346).
 */
export function chiffreDeControle(prefixe: string): number {
  if (!/^[A-Z]{4}[0-9]{6}$/.test(prefixe)) {
    throw new Error(`Préfixe ISO 6346 invalide (4 lettres + 6 chiffres attendus) : ${prefixe}`);
  }

  let somme = 0;
  for (let i = 0; i < 10; i++) {
    somme += valeurCaractere(prefixe.charAt(i)) * 2 ** i;
  }

  const reste = somme % 11;
  return reste === 10 ? 0 : reste;
}

/**
 * Vrai si le numéro fourni est un numéro de conteneur ISO 6346 valide
 * (forme canonique attendue : la normalisation éventuelle est à la charge de
 * l'appelant via normaliserNumeroConteneur).
 */
export function estNumeroConteneurValide(numero: string): boolean {
  if (!FORME_CANONIQUE.test(numero)) {
    return false;
  }

  const attendu = chiffreDeControle(numero.slice(0, 10));
  const fourni = Number(numero.charAt(10));

  return attendu === fourni;
}
