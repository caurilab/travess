import { useQuery } from '@tanstack/react-query';
import type { SensDossier, StatutDossier } from '@travess/shared-types';

import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Carte } from '../../ui/Carte.js';
import { montant } from '../../lib/format.js';
import { statutDossier } from '../../lib/statuts.js';
import { chargerArgentEnFeu, chargerSurestariesEvitees } from '../surestaries/api.js';
import { chargerStatistiquesDossiers } from './api.js';

const STATUTS: readonly StatutDossier[] = ['ouvert', 'en_cours', 'bloque', 'cloture'];

export function Rapports() {
  const stats = useQuery({
    queryKey: ['rapport', 'dossiers'],
    queryFn: ({ signal }) => chargerStatistiquesDossiers(signal),
  });
  const argent = useQuery({
    queryKey: ['dashboard', 'argent-en-feu'],
    queryFn: ({ signal }) => chargerArgentEnFeu(signal),
  });
  const evitees = useQuery({
    queryKey: ['dashboard', 'surestaries-evitees'],
    queryFn: ({ signal }) => chargerSurestariesEvitees(signal),
  });

  if (stats.isPending) {
    return (
      <div className="tb__chargement">
        <span className="tv-spinner" />
      </div>
    );
  }
  if (stats.isError) {
    return <p className="tb__erreur">Rapports indisponibles.</p>;
  }

  const d = stats.data;
  const maxStatut = Math.max(1, ...STATUTS.map((s) => d.par_statut[s]));

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Rapports</p>
          <h1 className="page__titre">Activité &amp; valeur</h1>
        </div>
      </header>

      <div className="rapport__kpis">
        <Kpi libelle="Dossiers actifs" valeur={String(d.actifs)} indice={`sur ${d.total} au total`} />
        <Kpi
          libelle="Argent qui brûle"
          valeur={argent.data ? montant(argent.data.menacant_cumule) : '—'}
          indice={argent.data ? `${argent.data.conteneurs_a_risque.length} conteneur(s) à risque` : ' '}
          risque
        />
        <Kpi
          libelle="Surestaries évitées (mois)"
          valeur={evitees.data ? montant(evitees.data.montant_evite) : '—'}
          indice={evitees.data ? `${evitees.data.nombre_conteneurs} conteneur(s) sortis à temps` : ' '}
        />
      </div>

      <div className="detail__grille">
        <Carte>
          <h2 className="detail__titre-carte">Dossiers par statut</h2>
          <ul className="rapport__barres">
            {STATUTS.map((s) => {
              const n = d.par_statut[s];
              const sd = statutDossier(s);
              return (
                <li key={s} className="rapport__barre">
                  <div className="rapport__barre-tete">
                    <BadgeStatut ton={sd.ton}>{sd.libelle}</BadgeStatut>
                    <span className="tv-tabulaire">{n}</span>
                  </div>
                  <div className="rapport__jauge">
                    <span
                      className={`rapport__jauge-remplissage rapport__jauge-remplissage--${sd.ton}`}
                      style={{ width: `${(n / maxStatut) * 100}%` }}
                    />
                  </div>
                </li>
              );
            })}
          </ul>
        </Carte>

        <Carte>
          <h2 className="detail__titre-carte">Répartition import / export</h2>
          <dl className="rapport__sens">
            <Sens libelle="Import" valeur={d.par_sens.import} total={d.total} ton="info" />
            <Sens libelle="Export" valeur={d.par_sens.export} total={d.total} ton="encours" />
          </dl>
        </Carte>
      </div>
    </div>
  );
}

function Kpi({
  libelle,
  valeur,
  indice,
  risque = false,
}: {
  readonly libelle: string;
  readonly valeur: string;
  readonly indice: string;
  readonly risque?: boolean;
}) {
  return (
    <Carte className="rapport__kpi">
      <span className="rapport__kpi-libelle">{libelle}</span>
      <span className={`rapport__kpi-valeur${risque ? ' rapport__kpi-valeur--risque' : ''} tv-tabulaire`}>{valeur}</span>
      <span className="rapport__kpi-indice">{indice}</span>
    </Carte>
  );
}

function Sens({
  libelle,
  valeur,
  total,
  ton,
}: {
  readonly libelle: string;
  readonly valeur: number;
  readonly total: number;
  readonly ton: 'info' | 'encours';
}) {
  const pct = total === 0 ? 0 : Math.round((valeur / total) * 100);
  return (
    <div className="rapport__sens-ligne">
      <dt>
        <BadgeStatut ton={ton}>{libelle}</BadgeStatut>
      </dt>
      <dd className="tv-tabulaire">
        {valeur} <span className="rapport__sens-pct">({pct} %)</span>
      </dd>
    </div>
  );
}
