import type { SensDossier, StatutDossier } from './enums.js';

/**
 * Statistiques d'activité des dossiers (miroir de GET /dossiers/statistiques).
 * Agrégats tenant-scopés pour l'écran Rapports.
 */
export interface StatistiquesDossiersDTO {
  readonly total: number;
  readonly actifs: number;
  readonly par_statut: Record<StatutDossier, number>;
  readonly par_sens: Record<SensDossier, number>;
}
