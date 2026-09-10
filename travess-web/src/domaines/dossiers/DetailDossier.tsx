import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { STATUTS_CONTENEUR, STATUTS_ETAPE, TYPES_CONTENEUR, type DossierDTO } from '@travess/shared-types';
import { useState, type FormEvent } from 'react';
import { Link, useParams } from 'react-router-dom';

import { ErreurRequete } from '../../api/client.js';
import { creerConteneur, mettreAJourConteneur } from '../conteneurs/api.js';
import { OngletCorrespondance } from '../correspondance/OngletCorrespondance.js';
import { OngletDocuments } from '../documents/OngletDocuments.js';
import { emettreInvitation, lienFrontInvitation } from '../portail/api.js';
import { OngletEcheances } from './OngletEcheances.js';
import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { Champ } from '../../ui/Champ.js';
import { dateCourte, montant } from '../../lib/format.js';
import { statutConteneur, statutDossier, statutEtape } from '../../lib/statuts.js';
import { chargerDossier, cloturerDossier, mettreAJourEtape } from './api.js';

type Onglet = 'parcours' | 'conteneurs' | 'echeances' | 'documents' | 'correspondance' | 'finances';

const ONGLETS: readonly { readonly cle: Onglet; readonly libelle: string }[] = [
  { cle: 'parcours', libelle: 'Parcours' },
  { cle: 'conteneurs', libelle: 'Conteneurs' },
  { cle: 'echeances', libelle: 'Échéances / Douane' },
  { cle: 'documents', libelle: 'Documents' },
  { cle: 'correspondance', libelle: 'Correspondance' },
  { cle: 'finances', libelle: 'Finances' },
];

export function DetailDossier() {
  const { id = '' } = useParams();
  const client = useQueryClient();
  const [onglet, setOnglet] = useState<Onglet>('parcours');

  const dossier = useQuery({
    queryKey: ['dossier', id],
    queryFn: ({ signal }) => chargerDossier(id, signal),
  });

  const rafraichir = () => {
    void client.invalidateQueries({ queryKey: ['dossier', id] });
  };
  const cloture = useMutation({
    mutationFn: (motif: string | null) => cloturerDossier(id, motif),
    onSuccess: rafraichir,
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
  const blPrincipal = d.bls?.[0];
  const nbConteneurs = (d.bls ?? []).reduce((n, bl) => n + (bl.conteneurs?.length ?? 0), 0);

  return (
    <div className="page">
      <div className="fiche__actions">
        <Link to="/dossiers" className="detail__retour">
          ← Retour aux dossiers
        </Link>
        {d.statut !== 'cloture' ? (
          <Bouton
            variante="secondaire"
            chargement={cloture.isPending}
            onClick={() => {
              const motif = window.prompt('Motif de clôture (facultatif) :') ?? '';
              cloture.mutate(motif === '' ? null : motif);
            }}
          >
            Clôturer le dossier
          </Bouton>
        ) : null}
      </div>

      <InviterClient dossierId={d.id} />

      {/* En-tête BL (centré sur le connaissement, comme la fiche métier). */}
      <Carte className="fiche__entete">
        <div className="fiche__entete-haut">
          <div>
            <p className="fiche__surtitre">Connaissement (BL)</p>
            <div className="fiche__bl">{blPrincipal?.numero ?? d.reference}</div>
            <div className="fiche__ligne-meta">
              <BadgeStatut ton={d.sens === 'import' ? 'info' : 'encours'}>
                {d.sens === 'import' ? 'Import' : 'Export'}
              </BadgeStatut>
              <span className="fiche__ref">{d.reference}</span>
              <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
            </div>
            <p className="fiche__client">{d.client?.nom ?? '—'}</p>
          </div>
        </div>
        <div className="fiche__grille-meta">
          <Meta libelle="Navire" valeur={blPrincipal?.navire_nom ?? '—'} />
          <Meta libelle="IMO" valeur={blPrincipal?.navire_imo ?? '—'} />
          <Meta libelle="Conteneurs" valeur={String(nbConteneurs)} />
          <Meta libelle="Blocage" valeur={d.motif_blocage ?? '—'} />
        </div>
      </Carte>

      {/* Onglets. */}
      <div className="fiche__onglets">
        {ONGLETS.map((o) => (
          <button
            key={o.cle}
            className={`fiche__onglet${onglet === o.cle ? ' fiche__onglet--actif' : ''}`}
            onClick={() => setOnglet(o.cle)}
          >
            {o.libelle}
          </button>
        ))}
      </div>

      {onglet === 'parcours' ? <OngletParcours dossier={d} onChangement={rafraichir} /> : null}
      {onglet === 'conteneurs' ? <OngletConteneurs dossier={d} onChangement={rafraichir} /> : null}
      {onglet === 'echeances' ? <OngletEcheances dossier={d} /> : null}
      {onglet === 'documents' ? <OngletDocuments dossier={d} onChangement={rafraichir} /> : null}
      {onglet === 'correspondance' ? <OngletCorrespondance dossier={d} /> : null}
      {onglet === 'finances' ? <OngletFinances dossier={d} /> : null}
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

function OngletParcours({ dossier, onChangement }: { readonly dossier: DossierDTO; readonly onChangement: () => void }) {
  const etape = useMutation({
    mutationFn: ({ etapeId, statut }: { etapeId: string; statut: string }) => mettreAJourEtape(etapeId, statut),
    onSuccess: onChangement,
  });

  return (
    <Carte>
      <h2 className="detail__titre-carte">Parcours du dossier</h2>
      <ol className="parcours">
        {(dossier.etapes ?? []).map((e) => {
          const se = statutEtape(e.statut);
          return (
            <li key={e.id} className="parcours__item">
              <span className={`parcours__point parcours__point--${se.ton}`} />
              <div className="parcours__corps">
                <div className="parcours__ligne">
                  <span className="parcours__libelle">
                    {e.ordre}. {e.libelle}
                  </span>
                  <select
                    className="detail__select-mini"
                    value={e.statut}
                    disabled={etape.isPending}
                    onChange={(ev) => etape.mutate({ etapeId: e.id, statut: ev.target.value })}
                  >
                    {STATUTS_ETAPE.map((s) => (
                      <option key={s} value={s}>
                        {statutEtape(s).libelle}
                      </option>
                    ))}
                  </select>
                </div>
                <span className="parcours__date">
                  Prévu {dateCourte(e.date_prevue)}
                  {e.date_reelle !== null ? ` · fait ${dateCourte(e.date_reelle)}` : ''}
                </span>
              </div>
            </li>
          );
        })}
        {(dossier.etapes ?? []).length === 0 ? <p className="tb__vide">Aucune étape.</p> : null}
      </ol>
    </Carte>
  );
}

function OngletConteneurs({ dossier, onChangement }: { readonly dossier: DossierDTO; readonly onChangement: () => void }) {
  const conteneur = useMutation({
    mutationFn: ({ conteneurId, statut }: { conteneurId: string; statut: string }) =>
      mettreAJourConteneur(conteneurId, statut),
    onSuccess: onChangement,
  });

  return (
    <Carte>
      <h2 className="detail__titre-carte">Conteneurs · suivi &amp; surestaries</h2>
      {(dossier.bls ?? []).map((bl) => (
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
              {(bl.conteneurs ?? []).map((c) => (
                <tr key={c.id}>
                  <td className="tb__numero">{c.numero}</td>
                  <td>{c.type.toUpperCase()}</td>
                  <td>
                    <select
                      className="detail__select-mini"
                      value={c.statut}
                      disabled={conteneur.isPending}
                      onChange={(ev) => conteneur.mutate({ conteneurId: c.id, statut: ev.target.value })}
                    >
                      {STATUTS_CONTENEUR.map((s) => (
                        <option key={s} value={s}>
                          {statutConteneur(s).libelle}
                        </option>
                      ))}
                    </select>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          <AjouterConteneur blId={bl.id} onAjout={onChangement} />
        </div>
      ))}
      {(dossier.bls ?? []).length === 0 ? <p className="tb__vide">Aucun BL.</p> : null}
    </Carte>
  );
}

function OngletFinances({ dossier }: { readonly dossier: DossierDTO }) {
  return (
    <div className="detail__grille">
      <Carte>
        <h2 className="detail__titre-carte">Suivi financier</h2>
        <dl className="finances">
          <div>
            <dt>Charges (argent sorti)</dt>
            <dd className="tv-tabulaire">{montant(dossier.finances?.charges ?? 0)}</dd>
          </div>
          <div>
            <dt>Encaissements client</dt>
            <dd className="tv-tabulaire">{montant(dossier.finances?.encaissements ?? 0)}</dd>
          </div>
          <div>
            <dt>Honoraires</dt>
            <dd className="tv-tabulaire">{montant(dossier.finances?.honoraires ?? 0)}</dd>
          </div>
          <div className="finances__solde">
            <dt>Solde</dt>
            <dd className="tv-tabulaire">{montant(dossier.finances?.solde ?? 0)}</dd>
          </div>
        </dl>
      </Carte>
      <Carte>
        <h2 className="detail__titre-carte">Agents</h2>
        {(dossier.agents ?? []).length === 0 ? (
          <p className="tb__vide">Aucun agent assigné.</p>
        ) : (
          <ul className="agents">
            {(dossier.agents ?? []).map((a) => (
              <li key={a.id}>{a.nom}</li>
            ))}
          </ul>
        )}
      </Carte>
    </div>
  );
}

function AjouterConteneur({ blId, onAjout }: { readonly blId: string; readonly onAjout: () => void }) {
  const [numero, setNumero] = useState('');
  const [type, setType] = useState<string>(TYPES_CONTENEUR[0]);
  const [erreur, setErreur] = useState<string | null>(null);

  const ajout = useMutation({
    mutationFn: () => creerConteneur(blId, numero.trim().toUpperCase(), type),
    onSuccess: () => {
      setNumero('');
      setErreur(null);
      onAjout();
    },
    onError: () => setErreur('Numéro invalide (ISO 6346) ou déjà présent.'),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (numero.trim() === '') return;
    ajout.mutate();
  }

  return (
    <form className="ajout-conteneur" onSubmit={soumettre}>
      <input
        className="tv-champ__entree ajout-conteneur__numero"
        placeholder="Numéro conteneur"
        value={numero}
        onChange={(e) => setNumero(e.target.value)}
      />
      <select className="detail__select-mini" value={type} onChange={(e) => setType(e.target.value)}>
        {TYPES_CONTENEUR.map((t) => (
          <option key={t} value={t}>
            {t.toUpperCase()}
          </option>
        ))}
      </select>
      <Bouton type="submit" variante="secondaire" chargement={ajout.isPending}>
        Ajouter un conteneur
      </Bouton>
      {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
    </form>
  );
}

function InviterClient({ dossierId }: { readonly dossierId: string }) {
  const [ouvert, setOuvert] = useState(false);
  const [email, setEmail] = useState('');
  const [lien, setLien] = useState<string | null>(null);
  const [copie, setCopie] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);

  const emission = useMutation({
    mutationFn: () => emettreInvitation(dossierId, { canal: 'email', destinataire: email.trim() }),
    onSuccess: (r) => {
      setLien(lienFrontInvitation(r.lien));
      setErreur(null);
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : "Émission impossible."),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (email.trim() === '') return;
    emission.mutate();
  }

  if (!ouvert) {
    return (
      <div className="fiche__inviter">
        <Bouton variante="fantome" onClick={() => setOuvert(true)}>
          Inviter le client au portail
        </Bouton>
      </div>
    );
  }

  return (
    <Carte className="inviter">
      <h2 className="detail__titre-carte">Inviter le client au portail</h2>
      <form className="inviter__form" onSubmit={soumettre}>
        <Champ
          label="E-mail du client"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
        />
        <Bouton type="submit" chargement={emission.isPending} disabled={email.trim() === ''}>
          Générer le lien d'invitation
        </Bouton>
        {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
      </form>

      {lien !== null ? (
        <div className="inviter__lien">
          <p className="inviter__lien-titre">Lien d'accès (valable 72 h) — un e-mail a aussi été envoyé :</p>
          <code className="inviter__lien-valeur">{lien}</code>
          <Bouton
            variante="secondaire"
            onClick={() => {
              void navigator.clipboard?.writeText(lien).then(() => {
                setCopie(true);
                window.setTimeout(() => setCopie(false), 2000);
              });
            }}
          >
            {copie ? 'Copié ✓' : 'Copier le lien'}
          </Bouton>
        </div>
      ) : null}
    </Carte>
  );
}
