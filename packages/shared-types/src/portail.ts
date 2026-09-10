import type { SensDossier, StatutConteneur, StatutDossier, TypeConteneur } from './enums.js';

/**
 * Projection « parcours » (tracking) d'un conteneur pour le portail client
 * (liste blanche ADR-013 : jamais le snapshot brut ni l'ordonnancement interne).
 */
export interface ParcoursPortailDTO {
  readonly statut_conteneur: string | null;
  readonly emplacement: string | null;
  readonly eta_destination: string | null;
  readonly navire_nom: string | null;
  readonly capture_le: string | null;
}

/** Conteneur projeté pour le portail (miroir de ConteneurPortailResource). */
export interface ConteneurPortailDTO {
  readonly id: string;
  readonly numero: string;
  readonly type: TypeConteneur;
  readonly statut: StatutConteneur;
  readonly parcours: ParcoursPortailDTO | null;
}

/** BL projeté pour le portail (miroir de BlPortailResource). */
export interface BlPortailDTO {
  readonly id: string;
  readonly numero: string;
  readonly navire_nom: string | null;
  readonly navire_imo: string | null;
  readonly conteneurs: readonly ConteneurPortailDTO[];
}

/**
 * Dossier en vue LIMITÉE du client (miroir de DossierPortailResource) :
 * identité + BL + conteneurs, jamais les finances ni le motif de blocage.
 */
export interface DossierPortailDTO {
  readonly id: string;
  readonly reference: string;
  readonly sens: SensDossier;
  readonly statut: StatutDossier;
  readonly bls?: readonly BlPortailDTO[];
}
