import { useMutation } from '@tanstack/react-query';
import type { Enrolement2faDTO } from '@travess/shared-types';
import { useState } from 'react';

import { useAuth } from '../../auth/AuthProvider.js';
import { ErreurRequete } from '../../api/client.js';
import { Bouton } from '../../ui/Bouton.js';
import { Champ } from '../../ui/Champ.js';
import { Carte } from '../../ui/Carte.js';
import { activer2fa, confirmer2fa, definirVisibiliteAnnuaire, desactiver2fa } from './api.js';

export function Reglages() {
  const { moi } = useAuth();
  const tenant = moi?.tenant;
  const estGerant = moi?.user.role === 'gerant';

  const [annuaire, setAnnuaire] = useState<boolean>(tenant?.annuaire_public ?? false);
  const [erreur, setErreur] = useState<string | null>(null);

  const bascule = useMutation({
    mutationFn: (v: boolean) => definirVisibiliteAnnuaire(v),
    onSuccess: (r) => {
      setAnnuaire(r.annuaire_public);
      setErreur(null);
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Modification impossible.'),
  });

  return (
    <div className="page">
      <header className="page__entete">
        <div>
          <p className="page__surtitre">Réglages</p>
          <h1 className="page__titre">Mon agence</h1>
        </div>
      </header>

      <div className="detail__grille">
        <Carte>
          <h2 className="detail__titre-carte">Identité</h2>
          <dl className="finances">
            <div>
              <dt>Agence</dt>
              <dd>{tenant?.nom ?? '—'}</dd>
            </div>
            <div>
              <dt>Formule</dt>
              <dd>{tenant?.plan ?? '—'}</dd>
            </div>
            <div>
              <dt>Quota IA mensuel</dt>
              <dd className="tv-tabulaire">{tenant?.quota_ia_mensuel ?? '—'}</dd>
            </div>
            <div>
              <dt>Quota tracking mensuel</dt>
              <dd className="tv-tabulaire">{tenant?.quota_tracking_mensuel ?? '—'}</dd>
            </div>
          </dl>
        </Carte>

        <Carte>
          <h2 className="detail__titre-carte">Annuaire de la plateforme</h2>
          <p className="reglages__aide">
            Rendez votre agence visible dans l'annuaire pour recevoir des demandes de prise en charge de la part de
            clients autonomes.
          </p>
          <label className="bascule">
            <input
              type="checkbox"
              checked={annuaire}
              disabled={!estGerant || bascule.isPending}
              onChange={(e) => bascule.mutate(e.target.checked)}
            />
            <span className="bascule__piste" aria-hidden />
            <span className="bascule__texte">{annuaire ? 'Visible dans l’annuaire' : 'Masqué de l’annuaire'}</span>
          </label>
          {!estGerant ? <p className="reglages__note">Seul un gérant peut modifier ce réglage.</p> : null}
          {erreur !== null ? <p className="tv-champ__erreur">{erreur}</p> : null}
        </Carte>
      </div>

      <Securite2fa />
    </div>
  );
}

function Securite2fa() {
  const { moi, rafraichir } = useAuth();
  const actif = moi?.user.deux_facteurs_actif ?? false;

  const [enrolement, setEnrolement] = useState<Enrolement2faDTO | null>(null);
  const [code, setCode] = useState('');
  const [erreur, setErreur] = useState<string | null>(null);

  const demarrer = useMutation({
    mutationFn: () => activer2fa(),
    onSuccess: (d) => {
      setEnrolement(d);
      setErreur(null);
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Activation impossible.'),
  });

  const confirmer = useMutation({
    mutationFn: () => confirmer2fa(code.trim()),
    onSuccess: async () => {
      setEnrolement(null);
      setCode('');
      setErreur(null);
      await rafraichir();
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Code invalide.'),
  });

  const desactiver = useMutation({
    mutationFn: () => desactiver2fa(window.prompt('Entrez un code d’authentification pour confirmer la désactivation :') ?? ''),
    onSuccess: async () => {
      setErreur(null);
      await rafraichir();
    },
    onError: (err) => setErreur(err instanceof ErreurRequete ? err.message : 'Désactivation impossible.'),
  });

  return (
    <Carte>
      <h2 className="detail__titre-carte">Sécurité · double authentification</h2>

      {actif ? (
        <div className="securite">
          <p className="securite__statut">
            <span className="securite__pastille securite__pastille--ok" /> La double authentification est active.
          </p>
          <Bouton variante="danger" chargement={desactiver.isPending} onClick={() => desactiver.mutate()}>
            Désactiver
          </Bouton>
        </div>
      ) : enrolement === null ? (
        <div className="securite">
          <p className="reglages__aide">
            Ajoutez une couche de sécurité : un code temporaire (application TOTP) sera demandé à la connexion.
          </p>
          <Bouton chargement={demarrer.isPending} onClick={() => demarrer.mutate()}>
            Activer la double authentification
          </Bouton>
        </div>
      ) : (
        <div className="securite">
          <p className="reglages__aide">
            Ajoutez ce compte à votre application d’authentification (clé de configuration ci-dessous), puis saisissez le
            code à 6 chiffres pour confirmer.
          </p>
          <div className="securite__secret">
            <span className="securite__label">Clé de configuration</span>
            <code className="inviter__lien-valeur">{enrolement.secret}</code>
          </div>
          {enrolement.codes_recuperation.length > 0 ? (
            <div className="securite__secret">
              <span className="securite__label">Codes de récupération (à conserver)</span>
              <code className="inviter__lien-valeur">{enrolement.codes_recuperation.join('  ·  ')}</code>
            </div>
          ) : null}
          <Champ label="Code à 6 chiffres" inputMode="numeric" value={code} onChange={(e) => setCode(e.target.value)} />
          <Bouton chargement={confirmer.isPending} disabled={code.trim() === ''} onClick={() => confirmer.mutate()}>
            Confirmer l’activation
          </Bouton>
        </div>
      )}

      {erreur !== null ? <p className="tv-champ__erreur">{erreur}</p> : null}
    </Carte>
  );
}
