import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';

import { useAuth } from '../../auth/AuthProvider.js';
import { ErreurRequete } from '../../api/client.js';
import { Carte } from '../../ui/Carte.js';
import { definirVisibiliteAnnuaire } from './api.js';

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
    </div>
  );
}
