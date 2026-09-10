import type {
  PostureDossier,
  SensDossier,
  StatutConteneur,
  StatutDemande,
  StatutDossier,
  StatutEtape,
  TypeConteneur,
} from './enums.js';

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

/** Étape de parcours projetée (miroir de EtapePortailResource). */
export interface EtapePortailDTO {
  readonly ordre: number;
  readonly libelle: string;
  readonly statut: StatutEtape;
  readonly date_prevue: string | null;
  readonly date_reelle: string | null;
}

/**
 * Vue ÉTENDUE d'un dossier que le client possède (surface autonome, miroir de
 * DossierAutonomeResource) : dossier + posture + étapes + BL + conteneurs.
 */
export interface DossierAutonomeDTO {
  readonly id: string;
  readonly reference: string;
  readonly sens: SensDossier;
  readonly statut: StatutDossier;
  readonly posture: PostureDossier;
  readonly etapes?: readonly EtapePortailDTO[];
  readonly bls?: readonly BlPortailDTO[];
}

/** Fiche annuaire d'un transitaire opt-in (miroir de TransitaireAnnuaireResource). */
export interface TransitaireAnnuaireDTO {
  readonly id: string;
  readonly nom: string;
}

/** Demande d'assignation (miroir de DemandeAssignationResource). */
export interface DemandeAssignationDTO {
  readonly id: string;
  readonly dossier_id: string;
  readonly statut: StatutDemande;
  readonly transitaire_cible_id: string;
  readonly tenant_demandeur_id: string;
  readonly motif_refus: string | null;
  readonly expire_at: string;
  readonly created_at: string | null;
}
