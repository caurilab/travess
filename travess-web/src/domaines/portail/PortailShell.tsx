import { NavLink, Outlet } from 'react-router-dom';

import { useAuth } from '../../auth/AuthProvider.js';
import { Bouton } from '../../ui/Bouton.js';

/**
 * Coquille du portail client (surface distincte de l'agent, intégrée à la même
 * app selon le rôle). Navigation minimale : le client ne voit que ses dossiers.
 */
export function PortailShell() {
  const { moi, deconnecter } = useAuth();

  return (
    <div className="shell">
      <aside className="shell__nav">
        <div className="shell__marque">Travess</div>
        <p className="portail__badge">Espace client</p>
        <nav className="shell__liens">
          <NavLink to="/" end className={({ isActive }) => `shell__lien${isActive ? ' shell__lien--actif' : ''}`}>
            Mes dossiers
          </NavLink>
        </nav>
        <div className="shell__pied">
          <div className="shell__agence">{moi?.user.nom}</div>
          <div className="shell__utilisateur">{moi?.tenant.nom}</div>
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
