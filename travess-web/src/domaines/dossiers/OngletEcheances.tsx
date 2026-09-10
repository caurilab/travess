import { useQueries } from '@tanstack/react-query';
import type { ConteneurDTO, DossierDTO, FranchiseDTO } from '@travess/shared-types';
import type { TonStatut } from '@travess/ui';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte, joursRestants, libelleEcheance, montant } from '../../lib/format.js';
import { libelleTypeFranchise, statutEtape } from '../../lib/statuts.js';
import { chargerFranchisesConteneur } from '../surestaries/api.js';

/** Ton d'un compte à rebours : échu = risque, imminent = en cours, sinon neutre. */
function tonEcheance(jours: number | null): TonStatut {
  if (jours === null) return 'neutre';
  if (jours < 0) return 'risque';
  if (jours <= 2) return 'encours';
  return 'neutre';
}

export function OngletEcheances({ dossier }: { readonly dossier: DossierDTO }) {
  const conteneurs = (dossier.bls ?? []).flatMap((bl) =>
    (bl.conteneurs ?? []).map((c) => ({ conteneur: c, blNumero: bl.numero })),
  );

  // Une requête par conteneur : react-query parallélise et met en cache.
  const requetes = useQueries({
    queries: conteneurs.map(({ conteneur }) => ({
      queryKey: ['franchises', conteneur.id],
      queryFn: ({ signal }: { signal: AbortSignal }) => chargerFranchisesConteneur(conteneur.id, signal),
    })),
  });

  const franchises = conteneurs.flatMap(({ conteneur, blNumero }, i) =>
    (requetes[i]?.data ?? [])
      .filter((f) => f.actif)
      .map((f) => ({ franchise: f, conteneur, blNumero })),
  );
  const chargement = requetes.some((r) => r.isPending);
  const totalMenacant = franchises.reduce((s, { franchise }) => s + franchise.montant_menacant, 0);
  const totalEnCours = franchises.reduce((s, { franchise }) => s + franchise.montant_en_cours, 0);

  return (
    <div className="detail__colonne">
      <Carte>
        <h2 className="detail__titre-carte">Échéancier du dossier</h2>
        <div className="table-scroll">
        <table className="tb__table echeances">
          <thead>
            <tr>
              <th>Jalon</th>
              <th>Date butoir</th>
              <th>Échéance</th>
              <th>État</th>
            </tr>
          </thead>
          <tbody>
            {(dossier.etapes ?? []).map((e) => {
              const se = statutEtape(e.statut);
              const fait = e.statut === 'fait';
              const jr = joursRestants(e.date_prevue);
              const ton: TonStatut = fait ? 'regle' : e.statut === 'en_retard' ? 'risque' : tonEcheance(jr);
              return (
                <tr key={e.id}>
                  <td>{e.libelle}</td>
                  <td className="tv-tabulaire">{dateCourte(e.date_prevue)}</td>
                  <td>
                    {fait ? (
                      <span className="echeances__faite">Fait le {dateCourte(e.date_reelle)}</span>
                    ) : (
                      <BadgeStatut ton={ton}>{libelleEcheance(jr)}</BadgeStatut>
                    )}
                  </td>
                  <td>
                    <BadgeStatut ton={se.ton}>{se.libelle}</BadgeStatut>
                  </td>
                </tr>
              );
            })}
            {(dossier.etapes ?? []).length === 0 ? (
              <tr>
                <td colSpan={4} className="tb__vide">
                  Aucun jalon.
                </td>
              </tr>
            ) : null}
          </tbody>
        </table>
        </div>
      </Carte>

      <Carte>
        <div className="echeances__entete">
          <h2 className="detail__titre-carte">Franchises &amp; surestaries</h2>
          {totalMenacant > 0 ? (
            <span className="echeances__menace">Menace {montant(totalMenacant)}</span>
          ) : null}
        </div>

        {chargement ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : franchises.length === 0 ? (
          <p className="tb__vide">Aucune franchise active sur ce dossier.</p>
        ) : (
          <div className="table-scroll">
          <table className="tb__table echeances">
            <thead>
              <tr>
                <th>Conteneur</th>
                <th>Type</th>
                <th>Fin de franchise</th>
                <th>Échéance</th>
                <th className="echeances__droite">En cours</th>
                <th className="echeances__droite">Menaçant</th>
              </tr>
            </thead>
            <tbody>
              {franchises.map(({ franchise, conteneur }) => (
                <LigneFranchise key={franchise.id} franchise={franchise} conteneur={conteneur} />
              ))}
            </tbody>
            <tfoot>
              <tr>
                <td colSpan={4}>Total dossier</td>
                <td className="echeances__droite tv-tabulaire">{montant(totalEnCours)}</td>
                <td className="echeances__droite tv-tabulaire">{montant(totalMenacant)}</td>
              </tr>
            </tfoot>
          </table>
          </div>
        )}
      </Carte>
    </div>
  );
}

function LigneFranchise({
  franchise,
  conteneur,
}: {
  readonly franchise: FranchiseDTO;
  readonly conteneur: ConteneurDTO;
}) {
  const enFacturation = franchise.montant_en_cours > 0;
  const jr = joursRestants(franchise.date_fin_franchise);

  return (
    <tr>
      <td className="tb__numero">{conteneur.numero}</td>
      <td>{libelleTypeFranchise(franchise.type)}</td>
      <td className="tv-tabulaire">{dateCourte(franchise.date_fin_franchise)}</td>
      <td>
        {enFacturation ? (
          <BadgeStatut ton="risque">En facturation</BadgeStatut>
        ) : (
          <BadgeStatut ton={tonEcheance(jr)}>{libelleEcheance(jr)}</BadgeStatut>
        )}
      </td>
      <td className={`echeances__droite tv-tabulaire${enFacturation ? ' echeances__danger' : ''}`}>
        {montant(franchise.montant_en_cours)}
      </td>
      <td className="echeances__droite tv-tabulaire">{montant(franchise.montant_menacant)}</td>
    </tr>
  );
}
