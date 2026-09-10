import type { InputHTMLAttributes } from 'react';
import { useId } from 'react';

interface Props extends InputHTMLAttributes<HTMLInputElement> {
  readonly label: string;
  readonly erreur?: string;
}

export function Champ({ label, erreur, id, ...reste }: Props) {
  const genere = useId();
  const identifiant = id ?? genere;

  return (
    <div className="tv-champ">
      <label className="tv-champ__label" htmlFor={identifiant}>
        {label}
      </label>
      <input
        id={identifiant}
        className="tv-champ__entree"
        aria-invalid={erreur !== undefined}
        {...reste}
      />
      {erreur !== undefined ? <span className="tv-champ__erreur">{erreur}</span> : null}
    </div>
  );
}
