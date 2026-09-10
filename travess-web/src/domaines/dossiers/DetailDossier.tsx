import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte, montant } from '../../lib/format.js';
import { libelleSens, statutConteneur, statutDossier, statutEtape } from '../../lib/statuts.js';
import { chargerDossier } from './api.js';

export function DetailDossier() {
  const { id = '' } = useParams();
  const dossier = useQuery({
    queryKey: ['dossier', id],
    queryFn: ({ signal }) => chargerDossier(id, signal),
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

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <Link to="/dossiers" className="detail__retour">
            ← Dossiers
          </Link>
          <h1 className="page__titre detail__titre">
            <span className="tb__numero">{d.reference}</span>
            <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
          </h1>
          <p className="detail__meta">
            {libelleSens(d.sens)} · {d.client?.nom ?? '—'}
            {d.motif_blocage !== null ? ` · Blocage : ${d.motif_blocage}` : ''}
          </p>
        </div>
      </header>

      <div className="detail__grille">
        <div className="detail__colonne">
          {/* Parcours (étapes du workflow) */}
          <Carte>
            <h2 className="detail__titre-carte">Parcours</h2>
            <ol className="parcours">
              {(d.etapes ?? []).map((e) => {
                const se = statutEtape(e.statut);
                return (
                  <li key={e.id} className="parcours__item">
                    <span className={`parcours__point parcours__point--${se.ton}`} />
                    <div className="parcours__corps">
                      <div className="parcours__ligne">
                        <span className="parcours__libelle">{e.libelle}</span>
                        <BadgeStatut ton={se.ton}>{se.libelle}</BadgeStatut>
                      </div>
                      <span className="parcours__date">
                        Prévu {dateCourte(e.date_prevue)}
                        {e.date_reelle !== null ? ` · fait ${dateCourte(e.date_reelle)}` : ''}
                      </span>
                    </div>
                  </li>
                );
              })}
            </ol>
          </Carte>

          {/* BL + conteneurs */}
          <Carte>
            <h2 className="detail__titre-carte">Connaissements</h2>
            {(d.bls ?? []).map((bl) => (
              <div key={bl.id} className="bl">
                <div className="bl__entete">
                  <span className="tb__numero">{bl.numero}</span>
                  <span className="bl__navire">{bl.navire_nom ?? 'Navire n/c'}</span>
                </div>
                <table className="tb__table">
                  <thead>
                    <tr>
                      <th>Conteneur</th>
                      <th>Type</th>
                      <th>Statut</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(bl.conteneurs ?? []).map((c) => {
                      const sc = statutConteneur(c.statut);
                      return (
                        <tr key={c.id}>
                          <td className="tb__numero">{c.numero}</td>
                          <td>{c.type.toUpperCase()}</td>
                          <td>
                            <BadgeStatut ton={sc.ton}>{sc.libelle}</BadgeStatut>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            ))}
            {(d.bls ?? []).length === 0 ? <p className="tb__vide">Aucun BL.</p> : null}
          </Carte>
        </div>

        <div className="detail__colonne detail__colonne--etroite">
          <Carte>
            <h2 className="detail__titre-carte">Finances</h2>
            <dl className="finances">
              <div>
                <dt>Charges</dt>
                <dd className="tv-tabulaire">{montant(d.finances?.charges ?? 0)}</dd>
              </div>
              <div>
                <dt>Encaissements</dt>
                <dd className="tv-tabulaire">{montant(d.finances?.encaissements ?? 0)}</dd>
              </div>
              <div>
                <dt>Honoraires</dt>
                <dd className="tv-tabulaire">{montant(d.finances?.honoraires ?? 0)}</dd>
              </div>
              <div className="finances__solde">
                <dt>Solde</dt>
                <dd className="tv-tabulaire">{montant(d.finances?.solde ?? 0)}</dd>
              </div>
            </dl>
          </Carte>

          <Carte>
            <h2 className="detail__titre-carte">Agents</h2>
            {(d.agents ?? []).length === 0 ? (
              <p className="tb__vide">Aucun agent assigné.</p>
            ) : (
              <ul className="agents">
                {(d.agents ?? []).map((a) => (
                  <li key={a.id}>{a.nom}</li>
                ))}
              </ul>
            )}
          </Carte>
        </div>
      </div>
    </div>
  );
}
