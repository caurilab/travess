import { useQuery } from '@tanstack/react-query';
import { STATUTS_DOSSIER, SENS_DOSSIER } from '@travess/shared-types';
import { useState } from 'react';
import { useNavigate } from 'react-router-dom';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte } from '../../lib/format.js';
import { libelleSens, statutDossier } from '../../lib/statuts.js';
import { listerDossiers } from './api.js';

export function ListeDossiers() {
  const naviguer = useNavigate();
  const [statut, setStatut] = useState('');
  const [sens, setSens] = useState('');

  const dossiers = useQuery({
    queryKey: ['dossiers', { statut, sens }],
    queryFn: ({ signal }) => listerDossiers({ statut, sens }, signal),
  });

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Dossiers</p>
          <h1 className="page__titre">Dossiers de transit</h1>
        </div>
      </header>

      <Carte>
        <div className="liste__filtres">
          <select className="liste__select" value={sens} onChange={(e) => setSens(e.target.value)}>
            <option value="">Tous sens</option>
            {SENS_DOSSIER.map((s) => (
              <option key={s} value={s}>
                {libelleSens(s)}
              </option>
            ))}
          </select>
          <select className="liste__select" value={statut} onChange={(e) => setStatut(e.target.value)}>
            <option value="">Tous statuts</option>
            {STATUTS_DOSSIER.map((s) => (
              <option key={s} value={s}>
                {statutDossier(s).libelle}
              </option>
            ))}
          </select>
        </div>

        {dossiers.isError ? (
          <p className="tb__erreur">Impossible de charger les dossiers.</p>
        ) : dossiers.isPending ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : dossiers.data.length === 0 ? (
          <p className="tb__vide">Aucun dossier.</p>
        ) : (
          <table className="tb__table">
            <thead>
              <tr>
                <th>Référence</th>
                <th>Sens</th>
                <th>Client</th>
                <th>Statut</th>
                <th>Créé le</th>
              </tr>
            </thead>
            <tbody>
              {dossiers.data.map((d) => {
                const st = statutDossier(d.statut);
                return (
                  <tr key={d.id} className="liste__ligne" onClick={() => naviguer(`/dossiers/${d.id}`)}>
                    <td className="tb__numero">{d.reference}</td>
                    <td>{libelleSens(d.sens)}</td>
                    <td>{d.client?.nom ?? '—'}</td>
                    <td>
                      <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
                    </td>
                    <td>{dateCourte(d.created_at?.slice(0, 10) ?? null)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </Carte>
    </div>
  );
}
