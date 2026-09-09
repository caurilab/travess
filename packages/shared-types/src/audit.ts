/**
 * Entrée du journal d'audit (miroir de AuditLogResource).
 */
export interface AuditLogDTO {
  readonly id: string;
  readonly user_id: string | null;
  readonly entite: string;
  readonly entite_id: string;
  readonly action: string;
  readonly avant: Record<string, unknown> | null;
  readonly apres: Record<string, unknown> | null;
  readonly at: string;
}
