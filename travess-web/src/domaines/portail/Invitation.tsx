import { useMutation, useQuery } from '@tanstack/react-query';
import { useState, type FormEvent } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';

import { ErreurRequete } from '../../api/client.js';
import { ecrireJeton } from '../../api/session.js';
import { Bouton } from '../../ui/Bouton.js';
import { Carte } from '../../ui/Carte.js';
import { Champ } from '../../ui/Champ.js';
import { confirmerInvitation, reclamerInvitation } from './onboarding.js';

/**
 * Page PUBLIQUE d'acceptation d'invitation (ADR-013). Le lien signé porte le
 * token (chemin) + la signature (query). À l'ouverture, on réclame l'invitation
 * (envoi de l'OTP) ; le client saisit le code pour activer son accès.
 */
export function Invitation() {
  const { token = '' } = useParams();
  const [params] = useSearchParams();
  const requeteSignee = params.toString();

  const apercu = useQuery({
    queryKey: ['invitation', token],
    queryFn: () => reclamerInvitation(token, requeteSignee),
    retry: false,
    refetchOnWindowFocus: false,
  });

  return (
    <div className="connexion">
      <Carte className="connexion__carte">
        <div className="connexion__marque">Travess</div>
        <p className="connexion__sous-titre">Accès à votre suivi de dossier</p>

        {apercu.isPending ? (
          <div className="tb__chargement">
            <span className="tv-spinner" />
          </div>
        ) : apercu.isError ? (
          <p className="connexion__erreur">
            {apercu.error instanceof ErreurRequete ? apercu.error.message : 'Invitation invalide ou expirée.'}
          </p>
        ) : (
          <FormulaireOtp token={token} destination={apercu.data.destination_masquee} canal={apercu.data.canal} />
        )}
      </Carte>
    </div>
  );
}

function FormulaireOtp({
  token,
  destination,
  canal,
}: {
  readonly token: string;
  readonly destination: string;
  readonly canal: string;
}) {
  const [code, setCode] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const confirmation = useMutation({
    mutationFn: () =>
      confirmerInvitation(token, {
        code_otp: code.trim(),
        ...(motDePasse.trim() !== '' ? { mot_de_passe: motDePasse.trim() } : {}),
      }),
    onSuccess: (r) => {
      // Jeton d'accès immédiat : on recharge en tant que client (portail).
      ecrireJeton(r.token);
      window.location.assign('/');
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Confirmation impossible.'),
  });

  function soumettre(ev: FormEvent) {
    ev.preventDefault();
    if (code.trim() === '') return;
    confirmation.mutate();
  }

  return (
    <form className="connexion__form" onSubmit={soumettre}>
      <p className="invitation__info">
        Un code de vérification a été envoyé par {canal === 'email' ? 'e-mail' : 'WhatsApp'} à{' '}
        <strong>{destination}</strong>.
      </p>
      <Champ
        label="Code de vérification"
        inputMode="numeric"
        autoComplete="one-time-code"
        value={code}
        onChange={(e) => setCode(e.target.value)}
      />
      <Champ
        label="Choisir un mot de passe (facultatif)"
        type="password"
        autoComplete="new-password"
        value={motDePasse}
        onChange={(e) => setMotDePasse(e.target.value)}
      />
      {erreur !== null ? <p className="connexion__erreur">{erreur}</p> : null}
      <Bouton bloc type="submit" chargement={confirmation.isPending} disabled={code.trim() === ''}>
        Accéder à mon dossier
      </Bouton>
    </form>
  );
}
