import { useAuth } from '../auth/AuthProvider.js';
import { Carte } from '../ui/Carte.js';

export function Accueil() {
  const { moi } = useAuth();

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Tableau de bord</p>
          <h1 className="page__titre">Bonjour, {moi?.user.nom}</h1>
        </div>
      </header>

      <Carte>
        <p className="page__vide">
          Le tableau de bord surestaries (« l'argent qui brûle ») arrive au prochain incrément.
        </p>
      </Carte>
    </div>
  );
}
