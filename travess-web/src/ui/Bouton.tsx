import type { ButtonHTMLAttributes, ReactNode } from 'react';

type Variante = 'primaire' | 'secondaire' | 'fantome' | 'danger';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  readonly variante?: Variante;
  readonly bloc?: boolean;
  readonly chargement?: boolean;
  readonly children: ReactNode;
}

export function Bouton({ variante = 'primaire', bloc = false, chargement = false, children, disabled, className, ...reste }: Props) {
  const classes = ['tv-bouton', `tv-bouton--${variante}`, bloc ? 'tv-bouton--bloc' : '', className ?? '']
    .filter(Boolean)
    .join(' ');

  return (
    <button className={classes} disabled={disabled === true || chargement} {...reste}>
      {chargement ? <span className="tv-spinner" aria-hidden /> : null}
      {children}
    </button>
  );
}
