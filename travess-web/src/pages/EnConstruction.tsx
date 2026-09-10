import { Carte } from '../ui/Carte.js';

export function EnConstruction({ titre }: { readonly titre: string }) {
  return (
    <div className="page">
      <header className="page__entete">
        <h1 className="page__titre">{titre}</h1>
      </header>
      <Carte>
        <p className="page__vide">Écran en construction.</p>
      </Carte>
    </div>
  );
}
