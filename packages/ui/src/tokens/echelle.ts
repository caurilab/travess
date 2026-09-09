/**
 * Jetons d'espacement, rayon, ombre et typographie (échelle partagée).
 */

export const espacement = {
  0: '0',
  1: '4px',
  2: '8px',
  3: '12px',
  4: '16px',
  5: '20px',
  6: '24px',
  8: '32px',
  10: '40px',
  12: '48px',
} as const;

export const rayon = {
  sm: '6px',
  md: '10px',
  lg: '16px',
  pilule: '9999px',
} as const;

export const ombre = {
  sm: '0 1px 2px rgba(28, 25, 23, 0.06)',
  md: '0 4px 12px rgba(28, 25, 23, 0.08)',
  lg: '0 12px 28px rgba(28, 25, 23, 0.12)',
} as const;

export const typographie = {
  famille:
    "'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif",
  familleMono: "'JetBrains Mono', ui-monospace, 'SF Mono', Menlo, monospace",
  tailles: {
    xs: '12px',
    sm: '13px',
    base: '14px',
    lg: '16px',
    xl: '20px',
    '2xl': '24px',
    '3xl': '30px',
  },
  graisses: {
    normal: 400,
    moyen: 500,
    semi: 600,
    gras: 700,
  },
} as const;
