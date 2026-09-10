import { useState, type FormEvent } from 'react';
import { useNavigate } from 'react-router-dom';

import { ErreurRequete } from '../api/client.js';
import { useAuth } from '../auth/AuthProvider.js';
import { Bouton } from '../ui/Bouton.js';
import { Carte } from '../ui/Carte.js';
import { Champ } from '../ui/Champ.js';

export function Connexion() {
  const { connecter } = useAuth();
  const naviguer = useNavigate();

  const [email, setEmail] = useState('');
  const [motDePasse, setMotDePasse] = useState('');
  const [code, setCode] = useState('');
  const [defi2fa, setDefi2fa] = useState(false);
  const [erreur, setErreur] = useState<string | null>(null);
  const [envoi, setEnvoi] = useState(false);

  async function soumettre(evenement: FormEvent) {
    evenement.preventDefault();
    setErreur(null);
    setEnvoi(true);
    try {
      const defi = await connecter(email, motDePasse, defi2fa ? code : undefined);
      if (defi === '2fa') {
        setDefi2fa(true);
        return;
      }
      naviguer('/', { replace: true });
    } catch (e) {
      setErreur(e instanceof ErreurRequete ? e.message : 'Connexion impossible.');
    } finally {
      setEnvoi(false);
    }
  }

  return (
    <div className="connexion">
      <Carte className="connexion__carte">
        <div className="connexion__marque">Travess</div>
        <p className="connexion__sous-titre">Espace transitaire</p>

        <form onSubmit={soumettre} className="connexion__form">
          <Champ
            label="E-mail"
            type="email"
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            disabled={defi2fa}
          />
          <Champ
            label="Mot de passe"
            type="password"
            autoComplete="current-password"
            value={motDePasse}
            onChange={(e) => setMotDePasse(e.target.value)}
            required
            disabled={defi2fa}
          />
          {defi2fa ? (
            <Champ
              label="Code d'authentification"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              required
              autoFocus
            />
          ) : null}

          {erreur !== null ? <p className="connexion__erreur">{erreur}</p> : null}

          <Bouton type="submit" bloc chargement={envoi}>
            {defi2fa ? 'Valider le code' : 'Se connecter'}
          </Bouton>
        </form>
      </Carte>
    </div>
  );
}
