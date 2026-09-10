import type { ReactNode } from 'react';
import { Navigate, Route, Routes } from 'react-router-dom';

import { useAuth } from '../auth/AuthProvider.js';
import { Alertes } from '../domaines/alertes/Alertes.js';
import { Clients } from '../domaines/clients/Clients.js';
import { DetailDossier } from '../domaines/dossiers/DetailDossier.js';
import { ListeDossiers } from '../domaines/dossiers/ListeDossiers.js';
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

export function Routeur() {
  const { statut } = useAuth();

  return (
    <Routes>
      <Route
        path="/connexion"
        element={statut === 'connecte' ? <Navigate to="/" replace /> : <Connexion />}
      />
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
        <Route path="/alertes" element={<Alertes />} />
        <Route path="/rapports" element={<Rapports />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
