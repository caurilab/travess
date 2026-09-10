/**
 * Client (donneur d'ordre) tel qu'exposé par l'API (miroir de ClientResource).
 */
export interface ClientDTO {
  readonly id: string;
  readonly nom: string;
  readonly contact: string | null;
  readonly canaux: {
    readonly email?: string;
    readonly whatsapp?: string;
  };
  readonly est_self: boolean;
}

/** Corps de POST /clients (et PATCH partiel). */
export interface CreerClientPayload {
  readonly nom: string;
  readonly contact?: string | null;
  readonly canaux?: {
    readonly email?: string;
    readonly whatsapp?: string;
  };
}
