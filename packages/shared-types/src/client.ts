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
}
