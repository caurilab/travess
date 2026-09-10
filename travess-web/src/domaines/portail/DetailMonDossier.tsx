import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte } from '../../lib/format.js';
import { libelleSens, statutConteneur, statutDossier } from '../../lib/statuts.js';
import { chargerMonDossier } from './api.js';

export function DetailMonDossier() {
  const { id = '' } = useParams();
  const dossier = useQuery({
    queryKey: ['portail', 'dossier', id],
    queryFn: ({ signal }) => chargerMonDossier(id, signal),
  });

  if (dossier.isPending) {
    return (
      <div className="tb__chargement">
        <span className="tv-spinner" />
      </div>
    );
  }
  if (dossier.isError) {
    return <p className="tb__erreur">Dossier introuvable.</p>;
  }

  const d = dossier.data;
  const st = statutDossier(d.statut);
  const bl = d.bls?.[0];

  return (
    <div className="page">
      <Link to="/" className="detail__retour">
        ← Mes dossiers
      </Link>

      <Carte className="fiche__entete">
        <p className="fiche__surtitre">Connaissement (BL)</p>
        <div className="fiche__bl">{bl?.numero ?? d.reference}</div>
        <div className="fiche__ligne-meta">
          <BadgeStatut ton={d.sens === 'import' ? 'info' : 'encours'}>{libelleSens(d.sens)}</BadgeStatut>
          <span className="fiche__ref">{d.reference}</span>
          <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
        </div>
        <div className="fiche__grille-meta">
          <Meta libelle="Navire" valeur={bl?.navire_nom ?? '—'} />
          <Meta libelle="IMO" valeur={bl?.navire_imo ?? '—'} />
        </div>
      </Carte>

      {(d.bls ?? []).map((b) => (
        <Carte key={b.id}>
          <h2 className="detail__titre-carte">Conteneurs · {b.numero}</h2>
          <div className="table-scroll">
            <table className="tb__table">
              <thead>
                <tr>
                  <th>Conteneur</th>
                  <th>Type</th>
                  <th>Statut</th>
                  <th>Dernier parcours</th>
                </tr>
              </thead>
              <tbody>
                {(b.conteneurs ?? []).map((c) => {
                  const sc = statutConteneur(c.statut);
                  return (
                    <tr key={c.id}>
                      <td className="tb__numero">{c.numero}</td>
                      <td>{c.type.toUpperCase()}</td>
                      <td>
                        <BadgeStatut ton={sc.ton}>{sc.libelle}</BadgeStatut>
                      </td>
                      <td className="portail__parcours">
                        {c.parcours !== null
                          ? `${c.parcours.emplacement ?? '—'}${
                              c.parcours.capture_le ? ` · ${dateCourte(c.parcours.capture_le.slice(0, 10))}` : ''
                            }`
                          : '—'}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </Carte>
      ))}
    </div>
  );
}

function Meta({ libelle, valeur }: { readonly libelle: string; readonly valeur: string }) {
  return (
    <div className="fiche__meta">
      <span className="fiche__meta-libelle">{libelle}</span>
      <span className="fiche__meta-valeur">{valeur}</span>
    </div>
  );
}
