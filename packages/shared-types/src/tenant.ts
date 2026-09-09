import type { PlanTenant, StatutTenant } from './enums.js';

/**
 * Tenant tel qu'exposé par l'API (miroir de TenantResource).
 */
export interface TenantDTO {
  readonly id: string;
  readonly nom: string;
  readonly plan: PlanTenant;
  readonly statut: StatutTenant;
  readonly quota_ia_mensuel: number;
  readonly quota_tracking_mensuel: number;
}
