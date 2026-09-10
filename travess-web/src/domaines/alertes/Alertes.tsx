import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { STATUTS_ALERTE } from '@travess/shared-types';
import { useState } from 'react';
import { Link } from 'react-router-dom';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte, montant } from '../../lib/format.js';
import { statutAlerte, typeAlerte } from '../../lib/statuts.js';
import { listerAlertes, marquerAlerte } from './api.js';

export function Alertes() {
  const client = useQueryClient();
  const [statut, setStatut] = useState('');

  const alertes = useQuery({
    queryKey: ['alertes', statut],
    queryFn: ({ signal }) => listerAlertes(statut, signal),
  });

  const mutation = useMutation({
    mutationFn: ({ id, vers }: { id: string; vers: 'vue' | 'traitee' }) => marquerAlerte(id, vers),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: ['alertes'] });
    },
  });

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Alertes</p>
          <h1 className="page__titre">Alertes surestaries</h1>
        </div>
      </header>

      <Carte>
        <div className="liste__filtres">
          <select className="liste__select" value={statut} onChange={(e) => setStatut(e.target.value)}>
            <option value="">Tous statuts</option>
            {STATUTS_ALERTE.map((s) => (
              <option key={s} value={s}>
                {statutAlerte(s).libelle}
              </option>
            ))}
          </select>
        </div>

        {alertes.isError ? (
          <p className="tb__erreur">Impossible de charger les alertes.</p>
        ) : alertes.isPending ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : alertes.data.length === 0 ? (
          <p className="tb__vide">Aucune alerte. 🎉</p>
        ) : (
          <table className="tb__table">
            <thead>
              <tr>
                <th>Type</th>
                <th className="tb__col-montant">Menaçant</th>
                <th>Statut</th>
                <th>Créée le</th>
                <th>Dossier</th>
                <th className="alertes__actions-col">Actions</th>
              </tr>
            </thead>
            <tbody>
              {alertes.data.map((a) => {
                const ta = typeAlerte(a.type);
                const sa = statutAlerte(a.statut);
                return (
                  <tr key={a.id}>
                    <td>
                      <BadgeStatut ton={ta.ton}>{ta.libelle}</BadgeStatut>
                    </td>
                    <td className="tb__col-montant tb__menacant tv-tabulaire">
                      {a.montant_menacant !== null ? montant(a.montant_menacant) : '—'}
                    </td>
                    <td>
                      <BadgeStatut ton={sa.ton}>{sa.libelle}</BadgeStatut>
                    </td>
                    <td>{dateCourte(a.created_at?.slice(0, 10) ?? null)}</td>
                    <td>
                      <Link to={`/dossiers/${a.dossier_id}`} className="alertes__lien">
                        Voir
                      </Link>
                    </td>
                    <td className="alertes__actions">
                      {a.statut === 'ouverte' ? (
                        <Bouton
                          variante="secondaire"
                          onClick={() => mutation.mutate({ id: a.id, vers: 'vue' })}
                          disabled={mutation.isPending}
                        >
                          Marquer vue
                        </Bouton>
                      ) : null}
                      {a.statut !== 'traitee' ? (
                        <Bouton
                          onClick={() => mutation.mutate({ id: a.id, vers: 'traitee' })}
                          disabled={mutation.isPending}
                        >
                          Traiter
                        </Bouton>
                      ) : null}
                    </td>
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
