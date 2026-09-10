import type { RoleUtilisateur } from './enums.js';
import type { TenantDTO } from './tenant.js';

/**
 * Utilisateur authentifié (miroir de UserResource).
 */
export interface UtilisateurDTO {
  readonly id: string;
  readonly nom: string;
  readonly email: string;
  readonly role: RoleUtilisateur;
  readonly deux_facteurs_actif: boolean;
}

/** Réponse de POST /auth/2fa/activer (enrôlement, secret exposé une seule fois). */
export interface Enrolement2faDTO {
  readonly secret: string;
  readonly otpauth_url: string;
  readonly codes_recuperation: readonly string[];
}

/**
 * Corps de POST /auth/login.
 */
export interface RequeteLogin {
  readonly email: string;
  readonly password: string;
}

/**
 * Réponse de login : soit un jeton, soit un défi d'authentification forte.
 */
export interface ReponseLoginJeton {
  readonly token: string;
}

export interface ReponseLoginDefi {
  readonly challenge: '2fa' | 'passkey';
}

export type ReponseLogin = ReponseLoginJeton | ReponseLoginDefi;

/**
 * Réponse de GET /auth/me : identité, tenant et permissions résolues.
 */
export interface ReponseMoi {
  readonly user: UtilisateurDTO;
  readonly tenant: TenantDTO;
  readonly permissions: readonly string[];
}
