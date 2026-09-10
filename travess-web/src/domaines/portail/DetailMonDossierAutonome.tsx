import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { TYPES_CONTENEUR } from '@travess/shared-types';
import { useState, type FormEvent } from 'react';
import { Link, useParams } from 'react-router-dom';

import { ErreurRequete } from '../../api/client.js';
import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { Champ } from '../../ui/Champ.js';
import { dateCourte } from '../../lib/format.js';
import { libelleSens, statutConteneur, statutDossier, statutEtape } from '../../lib/statuts.js';
import {
  ajouterBlAutonome,
  ajouterConteneurAutonome,
  chargerAnnuaire,
  chargerMonDossierAutonome,
  demanderAssignation,
} from './api.js';

export function DetailMonDossierAutonome() {
  const { id = '' } = useParams();
  const client = useQueryClient();

  const dossier = useQuery({
    queryKey: ['portail', 'autonome', id],
    queryFn: ({ signal }) => chargerMonDossierAutonome(id, signal),
  });

  const rafraichir = () => client.invalidateQueries({ queryKey: ['portail', 'autonome', id] });

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
        <p className="fiche__surtitre">Dossier</p>
        <div className="fiche__bl">{bl?.numero ?? d.reference}</div>
        <div className="fiche__ligne-meta">
          <BadgeStatut ton={d.sens === 'import' ? 'info' : 'encours'}>{libelleSens(d.sens)}</BadgeStatut>
          <span className="fiche__ref">{d.reference}</span>
          <BadgeStatut ton={st.ton}>{st.libelle}</BadgeStatut>
        </div>
      </Carte>

      <div className="detail__grille">
        <div className="detail__colonne">
          <Carte>
            <h2 className="detail__titre-carte">Parcours</h2>
            <ol className="parcours">
              {(d.etapes ?? []).map((e) => {
                const se = statutEtape(e.statut);
                return (
                  <li key={e.ordre} className="parcours__item">
                    <span className={`parcours__point parcours__point--${se.ton}`} />
                    <div className="parcours__corps">
                      <div className="parcours__ligne">
                        <span className="parcours__libelle">
                          {e.ordre}. {e.libelle}
                        </span>
                        <BadgeStatut ton={se.ton}>{se.libelle}</BadgeStatut>
                      </div>
                      <span className="parcours__date">Prévu {dateCourte(e.date_prevue)}</span>
                    </div>
                  </li>
                );
              })}
              {(d.etapes ?? []).length === 0 ? <p className="tb__vide">Aucune étape.</p> : null}
            </ol>
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
                        </tr>
                      );
                    })}
                    {(b.conteneurs ?? []).length === 0 ? (
                      <tr>
                        <td colSpan={3} className="tb__vide">
                          Aucun conteneur.
                        </td>
                      </tr>
                    ) : null}
                  </tbody>
                </table>
              </div>
              <AjouterConteneur blId={b.id} onAjout={rafraichir} />
            </Carte>
          ))}

          {(d.bls ?? []).length === 0 ? <AjouterBl dossierId={d.id} onAjout={rafraichir} /> : null}
        </div>

        <ConfierTransitaire dossierId={d.id} />
      </div>
    </div>
  );
}

function AjouterBl({ dossierId, onAjout }: { readonly dossierId: string; readonly onAjout: () => void }) {
  const [numero, setNumero] = useState('');
  const [armateur, setArmateur] = useState('');
  const [navire, setNavire] = useState('');
  const [imo, setImo] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const ajout = useMutation({
    mutationFn: () =>
      ajouterBlAutonome(dossierId, {
        numero: numero.trim(),
        armateur: armateur.trim(),
        ...(navire.trim() !== '' ? { navire_nom: navire.trim() } : {}),
        ...(imo.trim() !== '' ? { navire_imo: imo.trim() } : {}),
      }),
    onSuccess: onAjout,
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Ajout impossible.'),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (numero.trim() === '' || armateur.trim() === '') return;
    ajout.mutate();
  }

  return (
    <Carte>
      <h2 className="detail__titre-carte">Ajouter un connaissement (BL)</h2>
      <form className="composer" onSubmit={soumettre}>
        <Champ label="Numéro de BL" value={numero} onChange={(e) => setNumero(e.target.value)} />
        <Champ label="Armateur" value={armateur} onChange={(e) => setArmateur(e.target.value)} />
        <Champ label="Navire (facultatif)" value={navire} onChange={(e) => setNavire(e.target.value)} />
        <Champ label="IMO (facultatif)" value={imo} onChange={(e) => setImo(e.target.value)} />
        {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
        <Bouton type="submit" chargement={ajout.isPending} disabled={numero.trim() === '' || armateur.trim() === ''}>
          Ajouter le BL
        </Bouton>
      </form>
    </Carte>
  );
}

function AjouterConteneur({ blId, onAjout }: { readonly blId: string; readonly onAjout: () => void }) {
  const [numero, setNumero] = useState('');
  const [type, setType] = useState<string>(TYPES_CONTENEUR[0]);
  const [erreur, setErreur] = useState<string | null>(null);

  const ajout = useMutation({
    mutationFn: () => ajouterConteneurAutonome(blId, { numero: numero.trim().toUpperCase(), type }),
    onSuccess: () => {
      setNumero('');
      setErreur(null);
      onAjout();
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Numéro invalide (ISO 6346).'),
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

function ConfierTransitaire({ dossierId }: { readonly dossierId: string }) {
  const [recherche, setRecherche] = useState('');
  const [choisi, setChoisi] = useState('');
  const [message, setMessage] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);
  const [envoyee, setEnvoyee] = useState(false);

  const annuaire = useQuery({
    queryKey: ['annuaire', recherche],
    queryFn: ({ signal }) => chargerAnnuaire(recherche, signal),
  });

  const demande = useMutation({
    mutationFn: () => demanderAssignation(dossierId, { transitaire_id: choisi, ...(message.trim() !== '' ? { message: message.trim() } : {}) }),
    onSuccess: () => {
      setEnvoyee(true);
      setErreur(null);
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Demande impossible.'),
  });

  if (envoyee) {
    return (
      <Carte>
        <h2 className="detail__titre-carte">Confier à un transitaire</h2>
        <p className="composer__succes">Demande envoyée. Le transitaire pourra l'accepter pour prendre le dossier en charge.</p>
      </Carte>
    );
  }

  return (
    <Carte>
      <h2 className="detail__titre-carte">Confier à un transitaire</h2>
      <div className="composer">
        <input
          className="tv-champ__entree"
          placeholder="Rechercher un transitaire…"
          value={recherche}
          onChange={(e) => setRecherche(e.target.value)}
        />
        <select className="liste__select" value={choisi} onChange={(e) => setChoisi(e.target.value)}>
          <option value="">— Choisir un transitaire —</option>
          {(annuaire.data ?? []).map((t) => (
            <option key={t.id} value={t.id}>
              {t.nom}
            </option>
          ))}
        </select>
        <textarea
          className="composer__corps"
          rows={4}
          placeholder="Message (facultatif)"
          value={message}
          onChange={(e) => setMessage(e.target.value)}
        />
        {erreur !== null ? <span className="tv-champ__erreur">{erreur}</span> : null}
        <Bouton bloc chargement={demande.isPending} disabled={choisi === ''} onClick={() => demande.mutate()}>
          Demander la prise en charge
        </Bouton>
        {(annuaire.data ?? []).length === 0 && !annuaire.isPending ? (
          <p className="tb__vide">Aucun transitaire disponible dans l'annuaire.</p>
        ) : null}
      </div>
    </Carte>
  );
}
