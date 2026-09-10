import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { SENS_DOSSIER, type DossierAutonomeDTO, type DossierPortailDTO, type SensDossier } from '@travess/shared-types';
import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';

import { ErreurRequete } from '../../api/client.js';
import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { libelleSens, statutDossier } from '../../lib/statuts.js';
import { creerDossierAutonome, listerMesDossiers, listerMesDossiersAutonomes } from './api.js';

export function MesDossiers() {
  const [creation, setCreation] = useState(false);
  const naviguer = useNavigate();

  const miens = useQuery({
    queryKey: ['portail', 'autonomes'],
    queryFn: ({ signal }) => listerMesDossiersAutonomes(signal),
  });
  const partages = useQuery({
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
        <Bouton onClick={() => setCreation((v) => !v)}>{creation ? 'Fermer' : 'Nouveau dossier'}</Bouton>
      </header>

      {creation ? <NouveauDossierAutonome onCree={(id) => naviguer(`/autonome/${id}`)} /> : null}

      <section className="portail__section">
        <h2 className="portail__section-titre">Mes dossiers</h2>
        {miens.isPending ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : miens.isError ? (
          <p className="tb__erreur">Chargement impossible.</p>
        ) : miens.data.length === 0 ? (
          <Carte>
            <p className="tb__vide">Vous n'avez pas encore de dossier. Créez-en un pour suivre votre marchandise.</p>
          </Carte>
        ) : (
          <div className="portail__cartes">
            {miens.data.map((d) => (
              <CarteDossier key={d.id} dossier={d} vers={`/autonome/${d.id}`} />
            ))}
          </div>
        )}
      </section>

      {(partages.data ?? []).length > 0 ? (
        <section className="portail__section">
          <h2 className="portail__section-titre">Partagés avec moi</h2>
          <div className="portail__cartes">
            {(partages.data ?? []).map((d) => (
              <CarteDossier key={d.id} dossier={d} vers={`/dossiers/${d.id}`} />
            ))}
          </div>
        </section>
      ) : null}
    </div>
  );
}

function CarteDossier({
  dossier,
  vers,
}: {
  readonly dossier: DossierPortailDTO | DossierAutonomeDTO;
  readonly vers: string;
}) {
  const st = statutDossier(dossier.statut);
  const bl = dossier.bls?.[0];
  const nbConteneurs = (dossier.bls ?? []).reduce((n, b) => n + (b.conteneurs?.length ?? 0), 0);
  return (
    <Link to={vers} className="portail__carte-lien">
      <Carte className="portail__carte">
        <div className="portail__carte-tete">
          <span className="tb__numero">{bl?.numero ?? dossier.reference}</span>
          <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
        </div>
        <p className="portail__carte-ref">
          {dossier.reference} · {libelleSens(dossier.sens)}
        </p>
        <p className="portail__carte-meta">
          {bl?.navire_nom ?? 'Navire n/c'} · {nbConteneurs} conteneur(s)
        </p>
      </Carte>
    </Link>
  );
}

function NouveauDossierAutonome({ onCree }: { readonly onCree: (id: string) => void }) {
  const client = useQueryClient();
  const [sens, setSens] = useState<SensDossier>('import');
  const [erreur, setErreur] = useState<string | null>(null);

  const creation = useMutation({
    mutationFn: () => creerDossierAutonome(sens),
    onSuccess: (d) => {
      void client.invalidateQueries({ queryKey: ['portail', 'autonomes'] });
      onCree(d.id);
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Création impossible.'),
  });

  return (
    <Carte>
      <h2 className="detail__titre-carte">Nouveau dossier</h2>
      <div className="nouveau-dossier">
        <select className="liste__select" value={sens} onChange={(e) => setSens(e.target.value as SensDossier)}>
          {SENS_DOSSIER.map((s) => (
            <option key={s} value={s}>
              {libelleSens(s)}
            </option>
          ))}
        </select>
        <Bouton chargement={creation.isPending} onClick={() => creation.mutate()}>
          Créer le dossier
        </Bouton>
        {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
      </div>
    </Carte>
  );
}
