import { useAuth } from '../auth/AuthProvider.js';
import { TableauBord } from '../domaines/surestaries/TableauBord.js';

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

      <TableauBord />
    </div>
  );
}
