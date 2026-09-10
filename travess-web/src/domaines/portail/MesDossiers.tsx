import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { libelleSens, statutDossier } from '../../lib/statuts.js';
import { listerMesDossiers } from './api.js';

export function MesDossiers() {
  const dossiers = useQuery({
    queryKey: ['portail', 'dossiers'],
    queryFn: ({ signal }) => listerMesDossiers(signal),
  });

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Suivi</p>
          <h1 className="page__titre">Mes dossiers</h1>
        </div>
      </header>

      {dossiers.isPending ? (
        <div className="tb__chargement">
          <span className="tv-spinner" />
        </div>
      ) : dossiers.isError ? (
        <p className="tb__erreur">Impossible de charger vos dossiers.</p>
      ) : dossiers.data.length === 0 ? (
        <Carte>
          <p className="tb__vide">Aucun dossier partagé pour le moment.</p>
        </Carte>
      ) : (
        <div className="portail__cartes">
          {dossiers.data.map((d) => {
            const st = statutDossier(d.statut);
            const bl = d.bls?.[0];
            const nbConteneurs = (d.bls ?? []).reduce((n, b) => n + (b.conteneurs?.length ?? 0), 0);
            return (
              <Link key={d.id} to={`/dossiers/${d.id}`} className="portail__carte-lien">
                <Carte className="portail__carte">
                  <div className="portail__carte-tete">
                    <span className="tb__numero">{bl?.numero ?? d.reference}</span>
                    <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
                  </div>
                  <p className="portail__carte-ref">
                    {d.reference} · {libelleSens(d.sens)}
                  </p>
                  <p className="portail__carte-meta">
                    {bl?.navire_nom ?? 'Navire n/c'} · {nbConteneurs} conteneur(s)
                  </p>
                </Carte>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
