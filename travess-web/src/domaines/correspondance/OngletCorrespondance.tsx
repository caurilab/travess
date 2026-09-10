import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { TYPES_DEMANDE, type DossierDTO, type MessageDTO, type TypeDemande } from '@travess/shared-types';
import { useState } from 'react';

import { ErreurRequete } from '../../api/client.js';
import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { Champ } from '../../ui/Champ.js';
import { dateCourte } from '../../lib/format.js';
import { libelleTypeDemande, statutMessage } from '../../lib/statuts.js';
import { envoyerMessage, genererBrouillon, listerMessages } from './api.js';

export function OngletCorrespondance({ dossier }: { readonly dossier: DossierDTO }) {
  const client = useQueryClient();
  const armateurId = dossier.bls?.[0]?.armateur_id ?? null;

  const messages = useQuery({
    queryKey: ['messages', dossier.id],
    queryFn: ({ signal }) => listerMessages(dossier.id, signal),
  });

  const rafraichir = () => client.invalidateQueries({ queryKey: ['messages', dossier.id] });

  return (
    <div className="detail__grille">
      <Carte>
        <h2 className="detail__titre-carte">Fil de correspondance</h2>
        {messages.isPending ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : messages.isError ? (
          <p className="tb__erreur">Fil indisponible.</p>
        ) : messages.data.length === 0 ? (
          <p className="tb__vide">Aucun message. Composez une demande à l'armateur ci-contre.</p>
        ) : (
          <ul className="fil">
            {messages.data.map((m) => (
              <LigneMessage key={m.id} message={m} />
            ))}
          </ul>
        )}
      </Carte>

      <Composer dossierId={dossier.id} armateurId={armateurId} onEnvoi={rafraichir} />
    </div>
  );
}

function LigneMessage({ message }: { readonly message: MessageDTO }) {
  const sm = statutMessage(message.statut);
  return (
    <li className={`fil__item fil__item--${message.direction}`}>
      <div className="fil__entete">
        <span className="fil__objet">{message.objet}</span>
        <BadgeStatut ton={sm.ton}>{sm.libelle}</BadgeStatut>
      </div>
      <div className="fil__meta">
        {message.type_demande !== null ? (
          <span className="fil__tag">{libelleTypeDemande(message.type_demande)}</span>
        ) : null}
        <span>→ {message.destinataire_adresse}</span>
        <span>{dateCourte(message.created_at?.slice(0, 10) ?? null)}</span>
      </div>
      <p className="fil__corps">{message.corps}</p>
      {message.erreur !== null ? <p className="fil__erreur">{message.erreur}</p> : null}
    </li>
  );
}

function Composer({
  dossierId,
  armateurId,
  onEnvoi,
}: {
  readonly dossierId: string;
  readonly armateurId: string | null;
  readonly onEnvoi: () => void;
}) {
  const [type, setType] = useState<TypeDemande>('relance_surestaries');
  const [objet, setObjet] = useState('');
  const [corps, setCorps] = useState('');
  const [destinataire, setDestinataire] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);
  const [succes, setSucces] = useState(false);

  const brouillon = useMutation({
    mutationFn: () => genererBrouillon(dossierId, type),
    onSuccess: (b) => {
      setObjet(b.objet);
      setCorps(b.corps);
      setErreur(null);
    },
  });

  const envoi = useMutation({
    mutationFn: () =>
      envoyerMessage(dossierId, {
        armateur_id: armateurId,
        canal: 'email',
        type_demande: type,
        destinataire_adresse: destinataire.trim() === '' ? null : destinataire.trim(),
        objet,
        corps,
      }),
    onSuccess: () => {
      setObjet('');
      setCorps('');
      setDestinataire('');
      setErreur(null);
      setSucces(true);
      onEnvoi();
    },
    onError: (err) => {
      setSucces(false);
      setErreur(err instanceof ErreurRequete ? err.message : 'Envoi impossible. Réessayez.');
    },
  });

  const pretAEnvoyer = objet.trim() !== '' && corps.trim() !== '';

  return (
    <Carte>
      <h2 className="detail__titre-carte">Demande par mail</h2>
      <div className="composer">
        <label className="composer__champ">
          <span className="composer__label">Type de demande</span>
          <select className="detail__select-mini" value={type} onChange={(e) => setType(e.target.value as TypeDemande)}>
            {TYPES_DEMANDE.map((t) => (
              <option key={t} value={t}>
                {libelleTypeDemande(t)}
              </option>
            ))}
          </select>
        </label>

        <Bouton variante="secondaire" chargement={brouillon.isPending} onClick={() => brouillon.mutate()}>
          Générer un brouillon
        </Bouton>

        <Champ
          label="Destinataire (facultatif)"
          placeholder="À défaut, l'e-mail de l'armateur"
          value={destinataire}
          onChange={(e) => setDestinataire(e.target.value)}
        />
        <Champ label="Objet" value={objet} onChange={(e) => setObjet(e.target.value)} />

        <label className="composer__champ">
          <span className="composer__label">Message</span>
          <textarea
            className="composer__corps"
            rows={12}
            value={corps}
            onChange={(e) => setCorps(e.target.value)}
            placeholder="Générez un brouillon puis ajustez le texte avant l'envoi."
          />
        </label>

        {erreur !== null ? <p className="tv-champ__erreur">{erreur}</p> : null}
        {succes ? <p className="composer__succes">Demande mise en file d'envoi.</p> : null}

        <Bouton bloc chargement={envoi.isPending} disabled={!pretAEnvoyer} onClick={() => envoi.mutate()}>
          Envoyer à l'armateur
        </Bouton>
      </div>
    </Carte>
  );
}
