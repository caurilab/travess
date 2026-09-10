import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';

import { ErreurRequete } from '../../api/client.js';
import { BadgeStatut } from '../../ui/BadgeStatut.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { Champ } from '../../ui/Champ.js';
import { creerClient, listerClients } from './api.js';

export function Clients() {
  const client = useQueryClient();
  const [recherche, setRecherche] = useState('');

  const clients = useQuery({
    queryKey: ['clients', recherche],
    queryFn: ({ signal }) => listerClients(recherche, signal),
  });

  const rafraichir = () => client.invalidateQueries({ queryKey: ['clients'] });

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Clients</p>
          <h1 className="page__titre">Donneurs d'ordre</h1>
        </div>
      </header>

      <div className="detail__grille">
        <Carte>
          <div className="liste__filtres">
            <input
              className="tv-champ__entree"
              placeholder="Rechercher un client…"
              value={recherche}
              onChange={(e) => setRecherche(e.target.value)}
            />
          </div>

          {clients.isPending ? (
            <div className="tb__chargement">
              <span className="tv-spinner" />
            </div>
          ) : clients.isError ? (
            <p className="tb__erreur">Clients indisponibles.</p>
          ) : clients.data.length === 0 ? (
            <p className="tb__vide">Aucun client.</p>
          ) : (
            <table className="tb__table">
              <thead>
                <tr>
                  <th>Nom</th>
                  <th>Contact</th>
                  <th>Canaux</th>
                </tr>
              </thead>
              <tbody>
                {clients.data.map((c) => (
                  <tr key={c.id}>
                    <td>
                      {c.nom}
                      {c.est_self ? <BadgeStatut ton="info">Portail</BadgeStatut> : null}
                    </td>
                    <td>{c.contact ?? '—'}</td>
                    <td className="clients__canaux">
                      {c.canaux.email ?? ''}
                      {c.canaux.email && c.canaux.whatsapp ? ' · ' : ''}
                      {c.canaux.whatsapp ?? ''}
                      {!c.canaux.email && !c.canaux.whatsapp ? '—' : ''}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </Carte>

        <NouveauClient onCree={rafraichir} />
      </div>
    </div>
  );
}

function NouveauClient({ onCree }: { readonly onCree: () => void }) {
  const [nom, setNom] = useState('');
  const [contact, setContact] = useState('');
  const [email, setEmail] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const creation = useMutation({
    mutationFn: () =>
      creerClient({
        nom: nom.trim(),
        contact: contact.trim() === '' ? null : contact.trim(),
        canaux: {
          ...(email.trim() !== '' ? { email: email.trim() } : {}),
          ...(whatsapp.trim() !== '' ? { whatsapp: whatsapp.trim() } : {}),
        },
      }),
    onSuccess: () => {
      setNom('');
      setContact('');
      setEmail('');
      setWhatsapp('');
      setErreur(null);
      onCree();
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Création impossible.'),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (nom.trim() === '') return;
    creation.mutate();
  }

  return (
    <Carte>
      <h2 className="detail__titre-carte">Nouveau client</h2>
      <form className="composer" onSubmit={soumettre}>
        <Champ label="Nom" value={nom} onChange={(e) => setNom(e.target.value)} />
        <Champ label="Contact (facultatif)" value={contact} onChange={(e) => setContact(e.target.value)} />
        <Champ label="E-mail (facultatif)" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
        <Champ label="WhatsApp (facultatif)" value={whatsapp} onChange={(e) => setWhatsapp(e.target.value)} />
        {erreur !== null ? <p className="tv-champ__erreur">{erreur}</p> : null}
        <Bouton bloc type="submit" chargement={creation.isPending} disabled={nom.trim() === ''}>
          Créer le client
        </Bouton>
      </form>
    </Carte>
  );
}
