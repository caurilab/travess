import { useMutation, useQuery } from '@tanstack/react-query';
import { SENS_DOSSIER, STATUTS_DOSSIER, type SensDossier } from '@travess/shared-types';
import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';

import { ErreurRequete } from '../../api/client.js';
import { listerClients } from '../clients/api.js';
import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte } from '../../lib/format.js';
import { libelleSens, statutDossier } from '../../lib/statuts.js';
import { creerDossier, listerDossiers } from './api.js';

export function ListeDossiers() {
  const naviguer = useNavigate();
  const [statut, setStatut] = useState('');
  const [sens, setSens] = useState('');
  const [creation, setCreation] = useState(false);

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
        <Bouton onClick={() => setCreation((v) => !v)}>
          {creation ? 'Fermer' : 'Nouveau dossier'}
        </Bouton>
      </header>

      {creation ? <NouveauDossier onCree={(id) => naviguer(`/dossiers/${id}`)} /> : null}

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

function NouveauDossier({ onCree }: { readonly onCree: (id: string) => void }) {
  const [sens, setSens] = useState<SensDossier>('import');
  const [clientId, setClientId] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const clients = useQuery({
    queryKey: ['clients', ''],
    queryFn: ({ signal }) => listerClients('', signal),
  });

  const creation = useMutation({
    mutationFn: () => creerDossier({ sens, client_id: clientId }),
    onSuccess: (d) => onCree(d.id),
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Création impossible.'),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (clientId === '') {
      setErreur('Choisissez un client.');
      return;
    }
    creation.mutate();
  }

  return (
    <Carte>
      <h2 className="detail__titre-carte">Nouveau dossier</h2>
      <form className="nouveau-dossier" onSubmit={soumettre}>
        <select className="liste__select" value={sens} onChange={(e) => setSens(e.target.value as SensDossier)}>
          {SENS_DOSSIER.map((s) => (
            <option key={s} value={s}>
              {libelleSens(s)}
            </option>
          ))}
        </select>
        <select className="liste__select" value={clientId} onChange={(e) => setClientId(e.target.value)}>
          <option value="">— Choisir un client —</option>
          {(clients.data ?? []).map((c) => (
            <option key={c.id} value={c.id}>
              {c.nom}
            </option>
          ))}
        </select>
        <Bouton type="submit" chargement={creation.isPending} disabled={clientId === ''}>
          Créer le dossier
        </Bouton>
        {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
      </form>
      {(clients.data ?? []).length === 0 && !clients.isPending ? (
        <p className="tb__vide">Aucun client : créez-en un dans l'onglet Clients d'abord.</p>
      ) : null}
    </Carte>
  );
}
