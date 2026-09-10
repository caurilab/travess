import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { DemandeAssignationDTO } from '@travess/shared-types';
import { useState } from 'react';

import { ErreurRequete } from '../../api/client.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { dateCourte } from '../../lib/format.js';
import { accepterAssignation, listerDemandesAssignation, refuserAssignation } from './api.js';

export function Assignations() {
  const demandes = useQuery({
    queryKey: ['demandes-assignation'],
    queryFn: ({ signal }) => listerDemandesAssignation(signal),
  });

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Portail</p>
          <h1 className="page__titre">Demandes de prise en charge</h1>
        </div>
      </header>

      {demandes.isPending ? (
        <div className="tb__chargement">
          <span className="tv-spinner" />
        </div>
      ) : demandes.isError ? (
        <p className="tb__erreur">Chargement impossible.</p>
      ) : demandes.data.length === 0 ? (
        <Carte>
          <p className="tb__vide">Aucune demande en attente.</p>
        </Carte>
      ) : (
        <div className="portail__cartes">
          {demandes.data.map((d) => (
            <LigneDemande key={d.id} demande={d} />
          ))}
        </div>
      )}
    </div>
  );
}

function LigneDemande({ demande }: { readonly demande: DemandeAssignationDTO }) {
  const client = useQueryClient();
  const [message, setMessage] = useState<string | null>(null);
  const [erreur, setErreur] = useState<string | null>(null);

  const rafraichir = () => client.invalidateQueries({ queryKey: ['demandes-assignation'] });

  const acceptation = useMutation({
    mutationFn: () => accepterAssignation(demande.id),
    onSuccess: (r) => {
      setMessage(`Dossier pris en charge${r.nouvelle_reference ? ` — nouvelle référence ${r.nouvelle_reference}` : ''}.`);
      rafraichir();
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Action impossible.'),
  });

  const refus = useMutation({
    mutationFn: () => refuserAssignation(demande.id, window.prompt('Motif du refus :') ?? 'Non précisé'),
    onSuccess: () => {
      setMessage('Demande refusée.');
      rafraichir();
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Action impossible.'),
  });

  return (
    <Carte className="portail__carte">
      <div className="portail__carte-tete">
        <span className="tb__numero">Dossier {demande.dossier_id.slice(0, 8)}…</span>
      </div>
      <p className="portail__carte-meta">
        Reçue le {dateCourte(demande.created_at?.slice(0, 10) ?? null)} · expire le{' '}
        {dateCourte(demande.expire_at.slice(0, 10))}
      </p>
      {message !== null ? (
        <p className="composer__succes">{message}</p>
      ) : (
        <div className="assignation__actions">
          <Bouton chargement={acceptation.isPending} onClick={() => acceptation.mutate()}>
            Accepter
          </Bouton>
          <Bouton variante="secondaire" chargement={refus.isPending} onClick={() => refus.mutate()}>
            Refuser
          </Bouton>
        </div>
      )}
      {erreur !== null ? <p className="tv-champ__erreur">{erreur}</p> : null}
    </Carte>
  );
}
