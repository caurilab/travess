import { contextBridge } from 'electron';

/**
 * Pont sécurisé entre la couche native et le bundle web (contextIsolation).
 *
 * Expose le strict minimum dans `window.travessDesktop`. En prod, `apiBase`
 * donne l'origine absolue de l'API (le web sert alors sans proxy) ; en dev, on
 * ne l'expose pas (le bundle utilise le proxy Vite relatif « /api »).
 *
 * C'est aussi le point d'entrée des futures capacités natives (impression, scan,
 * notifications, SQLite hors-ligne) — à exposer ici une par une, jamais en
 * ouvrant nodeIntegration.
 */
const apiBase = process.env.TRAVESS_API_BASE ?? 'http://127.0.0.1:8000';

contextBridge.exposeInMainWorld('travessDesktop', {
  // Absent en dev (proxy Vite) ; présent en prod (bundle statique sans proxy).
  ...(process.env.TRAVESS_DESKTOP_DEV === '1' ? {} : { apiBase }),
  plateforme: process.platform,
});
