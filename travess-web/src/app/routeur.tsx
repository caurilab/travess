import type { ReactNode } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import { useAuth } from '../auth/AuthProvider.js';
import { Alertes } from '../domaines/alertes/Alertes.js';
import { Assignations } from '../domaines/assignations/Assignations.js';
import { Clients } from '../domaines/clients/Clients.js';
import { DetailDossier } from '../domaines/dossiers/DetailDossier.js';
import { ListeDossiers } from '../domaines/dossiers/ListeDossiers.js';
import { DetailMonDossier } from '../domaines/portail/DetailMonDossier.js';
import { DetailMonDossierAutonome } from '../domaines/portail/DetailMonDossierAutonome.js';
import { Invitation } from '../domaines/portail/Invitation.js';
import { MesDossiers } from '../domaines/portail/MesDossiers.js';
import { PortailShell } from '../domaines/portail/PortailShell.js';
import { Rapports } from '../domaines/rapports/Rapports.js';
import { Accueil } from '../pages/Accueil.js';
import { Connexion } from '../pages/Connexion.js';
import { AppShell } from './AppShell.js';

function Protege({ children }: { readonly children: ReactNode }) {
  const { statut } = useAuth();

  if (statut === 'chargement') {
    return (
      <div className="plein-ecran">
        <span className="tv-spinner" />
      </div>
    );
  }
  if (statut === 'anonyme') {
    return <Navigate to="/connexion" replace />;
  }
  return children;
}

/** Surface agent (transitaire). */
function RouteurAgent() {
  const { statut } = useAuth();

  return (
    <Routes>
      <Route path="/connexion" element={statut === 'connecte' ? <Navigate to="/" replace /> : <Connexion />} />
      <Route path="/invitation/:token" element={<Invitation />} />
      <Route
        element={
          <Protege>
            <AppShell />
          </Protege>
        }
      >
        <Route path="/" element={<Accueil />} />
        <Route path="/dossiers" element={<ListeDossiers />} />
        <Route path="/dossiers/:id" element={<DetailDossier />} />
        <Route path="/clients" element={<Clients />} />
        <Route path="/demandes" element={<Assignations />} />
        <Route path="/alertes" element={<Alertes />} />
        <Route path="/rapports" element={<Rapports />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

/** Surface portail client (vue limitée). */
function RouteurPortail() {
  return (
    <Routes>
      <Route path="/invitation/:token" element={<Invitation />} />
      <Route
        element={
          <Protege>
            <PortailShell />
          </Protege>
        }
      >
        <Route path="/" element={<MesDossiers />} />
        <Route path="/autonome/:id" element={<DetailMonDossierAutonome />} />
        <Route path="/dossiers/:id" element={<DetailMonDossier />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}

export function Routeur() {
  const { moi } = useAuth();

  return moi?.user.role === 'client' ? <RouteurPortail /> : <RouteurAgent />;
}
