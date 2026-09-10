import type { ReponseLogin, ReponseMoi } from '@travess/shared-types';
import { createContext, use, useCallback, useEffect, useState, type ReactNode } from 'react';

import { api } from '../api/client.js';
import { effacerJeton, ecrireJeton, lireJeton } from '../api/session.js';

type Statut = 'chargement' | 'connecte' | 'anonyme';

interface ContexteAuth {
  readonly statut: Statut;
  readonly moi: ReponseMoi | null;
  /** Renvoie un défi ('2fa') si l'authentification forte est requise. */
  readonly connecter: (email: string, motDePasse: string, code?: string) => Promise<'2fa' | null>;
  readonly deconnecter: () => Promise<void>;
  readonly aLaPermission: (permission: string) => boolean;
  /** Recharge l'identité courante (/auth/me), après un changement (ex. 2FA). */
  readonly rafraichir: () => Promise<void>;
}

const Contexte = createContext<ContexteAuth | null>(null);

export function AuthProvider({ children }: { readonly children: ReactNode }) {
  const [statut, setStatut] = useState<Statut>(lireJeton() !== null ? 'chargement' : 'anonyme');
  const [moi, setMoi] = useState<ReponseMoi | null>(null);

  const chargerMoi = useCallback(async (): Promise<void> => {
    try {
      const reponse = await api.get<ReponseMoi>('/auth/me');
      setMoi(reponse);
      setStatut('connecte');
    } catch {
      effacerJeton();
      setMoi(null);
      setStatut('anonyme');
    }
  }, []);

  useEffect(() => {
    if (lireJeton() !== null) {
      void chargerMoi();
    }
  }, [chargerMoi]);

  const connecter = useCallback(
    async (email: string, motDePasse: string, code?: string): Promise<'2fa' | null> => {
      const reponse = await api.post<ReponseLogin>('/auth/login', {
        email,
        password: motDePasse,
        ...(code !== undefined && code !== '' ? { code } : {}),
      });

      if ('challenge' in reponse) {
        return reponse.challenge === '2fa' ? '2fa' : null;
      }

      ecrireJeton(reponse.token);
      setStatut('chargement');
      await chargerMoi();
      return null;
    },
    [chargerMoi],
  );

  const deconnecter = useCallback(async (): Promise<void> => {
    try {
      await api.post('/auth/logout');
    } catch {
      /* le jeton est peut-être déjà invalide ; on nettoie quand même */
    }
    effacerJeton();
    setMoi(null);
    setStatut('anonyme');
  }, []);

  const aLaPermission = useCallback(
    (permission: string): boolean => moi?.permissions.includes(permission) ?? false,
    [moi],
  );

  return (
    <Contexte value={{ statut, moi, connecter, deconnecter, aLaPermission, rafraichir: chargerMoi }}>
      {children}
    </Contexte>
  );
}

export function useAuth(): ContexteAuth {
  const contexte = use(Contexte);
  if (contexte === null) {
    throw new Error('useAuth doit être utilisé dans un AuthProvider.');
  }
  return contexte;
}
