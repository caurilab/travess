import { aspectPourTon, type TonStatut } from '@travess/ui';
import type { ReactNode } from 'react';

interface Props {
  readonly ton: TonStatut;
  readonly children: ReactNode;
}

export function BadgeStatut({ ton, children }: Props) {
  const aspect = aspectPourTon(ton);
  return (
    <span className="tv-badge" style={{ color: aspect.couleur, background: aspect.fond }}>
      <span className="tv-badge__point" />
      {children}
    </span>
  );
}
