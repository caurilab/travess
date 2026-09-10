import type {
  CanalMessage,
  DirectionMessage,
  StatutMessage,
  TypeDemande,
} from './enums.js';

/**
 * Message de correspondance armateur (miroir de MessageResource).
 * L'adresse du destinataire est figée à l'envoi (valeur probante).
 */
export interface MessageDTO {
  readonly id: string;
  readonly dossier_id: string;
  readonly armateur_id: string | null;
  readonly auteur_id: string | null;
  readonly direction: DirectionMessage;
  readonly type_demande: TypeDemande | null;
  readonly canal: CanalMessage;
  readonly destinataire_adresse: string;
  readonly objet: string;
  readonly corps: string;
  readonly statut: StatutMessage;
  readonly reference_externe: string | null;
  readonly erreur: string | null;
  readonly envoye_at: string | null;
  readonly recu_at: string | null;
  readonly created_at: string | null;
}

/**
 * Brouillon pré-rempli renvoyé par POST /dossiers/{id}/messages/brouillon.
 * Non persisté : l'agent l'édite avant envoi.
 */
export interface BrouillonMessageDTO {
  readonly objet: string;
  readonly corps: string;
  readonly type_demande: TypeDemande;
}

/**
 * Corps de POST /dossiers/{id}/messages. `destinataire_adresse` optionnel :
 * à défaut, l'e-mail de l'armateur est utilisé (422 si aucun).
 */
export interface CreerMessagePayload {
  readonly armateur_id?: string | null;
  readonly canal: CanalMessage;
  readonly type_demande?: TypeDemande | null;
  readonly destinataire_adresse?: string | null;
  readonly objet: string;
  readonly corps: string;
}
