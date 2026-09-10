import { NavLink, Outlet } from 'react-router-dom';

import { useAuth } from '../auth/AuthProvider.js';
import { Bouton } from '../ui/Bouton.js';

const NAVIGATION = [
  { vers: '/', libelle: 'Tableau de bord', exact: true },
  { vers: '/dossiers', libelle: 'Dossiers' },
  { vers: '/alertes', libelle: 'Alertes' },
  { vers: '/rapports', libelle: 'Rapports' },
] as const;

export function AppShell() {
  const { moi, deconnecter } = useAuth();

  return (
    <div className="shell">
      <aside className="shell__nav">
        <div className="shell__marque">Travess</div>
        <nav className="shell__liens">
          {NAVIGATION.map((item) => (
            <NavLink
              key={item.vers}
              to={item.vers}
              end={'exact' in item ? item.exact : false}
              className={({ isActive }) => `shell__lien${isActive ? ' shell__lien--actif' : ''}`}
            >
              {item.libelle}
            </NavLink>
          ))}
        </nav>
        <div className="shell__pied">
          <div className="shell__agence">{moi?.tenant.nom}</div>
          <div className="shell__utilisateur">{moi?.user.nom}</div>
          <Bouton variante="secondaire" bloc onClick={() => void deconnecter()}>
            Se déconnecter
          </Bouton>
        </div>
      </aside>
      <main className="shell__contenu">
        <Outlet />
      </main>
    </div>
  );
}
