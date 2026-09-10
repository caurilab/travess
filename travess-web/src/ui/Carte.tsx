import type { HTMLAttributes, ReactNode } from 'react';

interface Props extends HTMLAttributes<HTMLDivElement> {
  readonly children: ReactNode;
}

export function Carte({ children, className, ...reste }: Props) {
  return (
    <div className={['tv-carte', className ?? ''].filter(Boolean).join(' ')} {...reste}>
      {children}
    </div>
  );
}
