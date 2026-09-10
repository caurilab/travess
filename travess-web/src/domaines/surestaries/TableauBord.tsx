import { useQuery } from '@tanstack/react-query';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte, montant } from '../../lib/format.js';
import { chargerArgentEnFeu, chargerSurestariesEvitees } from './api.js';

export function TableauBord() {
  const argent = useQuery({
    queryKey: ['dashboard', 'argent-en-feu'],
    queryFn: ({ signal }) => chargerArgentEnFeu(signal),
  });
  const evitees = useQuery({
    queryKey: ['dashboard', 'surestaries-evitees'],
    queryFn: ({ signal }) => chargerSurestariesEvitees(signal),
  });

  return (
    <div className="tb">
      <div className="tb__cartes">
        {/* Hero : l'argent qui brûle. */}
        <Carte className="tb__feu">
          <p className="tb__feu-titre">Argent qui brûle</p>
          <p className="tb__feu-montant tv-tabulaire">
            {argent.isPending ? '…' : montant(argent.data?.menacant_cumule ?? 0)}
          </p>
          <p className="tb__feu-sous">
            En cours accumulé :{' '}
            <strong className="tv-tabulaire">
              {argent.isPending ? '…' : montant(argent.data?.en_cours_cumule ?? 0)}
            </strong>
          </p>
        </Carte>

        {/* Métrique de valeur : surestaries évitées ce mois. */}
        <Carte className="tb__evite">
          <p className="tb__stat-titre">Surestaries évitées ce mois</p>
          <p className="tb__stat-montant tv-tabulaire">
            {evitees.isPending ? '…' : montant(evitees.data?.montant_evite ?? 0)}
          </p>
          <p className="tb__stat-sous">
            {evitees.isPending
              ? '…'
              : `${evitees.data?.nombre_conteneurs ?? 0} conteneur(s) sortis à temps`}
          </p>
        </Carte>
      </div>

      <Carte>
        <div className="tb__entete-table">
          <h2 className="tb__table-titre">Conteneurs à risque</h2>
          <span className="tb__compteur">
            {argent.data ? `${argent.data.conteneurs_a_risque.length} en alerte` : ''}
          </span>
        </div>

        {argent.isError ? (
          <p className="tb__erreur">Impossible de charger le tableau de bord.</p>
        ) : argent.isPending ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : argent.data.conteneurs_a_risque.length === 0 ? (
          <p className="tb__vide">Aucun conteneur à risque. 🎉</p>
        ) : (
          <table className="tb__table">
            <thead>
              <tr>
                <th>Conteneur</th>
                <th>Type</th>
                <th>Fin de franchise</th>
                <th className="tb__col-montant">En cours</th>
                <th className="tb__col-montant">Menaçant</th>
              </tr>
            </thead>
            <tbody>
              {argent.data.conteneurs_a_risque.map((ligne) => (
                <tr key={ligne.franchise_id}>
                  <td className="tb__numero">{ligne.numero}</td>
                  <td>
                    <BadgeStatut ton={ligne.type === 'surestaries' ? 'risque' : 'encours'}>
                      {ligne.type === 'surestaries' ? 'Surestaries' : 'Détention'}
                    </BadgeStatut>
                  </td>
                  <td>{dateCourte(ligne.date_fin_franchise)}</td>
                  <td className="tb__col-montant tv-tabulaire">{montant(ligne.montant_en_cours)}</td>
                  <td className="tb__col-montant tb__menacant tv-tabulaire">
                    {montant(ligne.montant_menacant)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Carte>
    </div>
  );
}
