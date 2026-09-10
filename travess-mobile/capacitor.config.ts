import type { CapacitorConfig } from '@capacitor/cli';

/**
 * Configuration Capacitor de Travess mobile (principe n°8/9 : on réutilise le
 * bundle web, on ne réécrit pas l'app).
 *
 * - `webDir` = ./www : le bundle web y est copié par scripts/preparer-bundle.mjs
 *   (build de travess-web avec VITE_API_ORIGINE injecté).
 * - Live-reload sur appareil : poser CAP_SERVER_URL=http://<IP-du-Mac>:5180 avant
 *   `cap sync` fait charger le bundle servi par le Mac (équivalent d'un Expo Go),
 *   le téléphone se recharge à chaque changement. Sans cette variable, l'app est
 *   autonome (bundle embarqué).
 */
const urlDev = process.env.CAP_SERVER_URL;

const config: CapacitorConfig = {
  appId: 'ci.travess.app',
  appName: 'Travess',
  webDir: 'www',
  ...(urlDev
    ? {
        server: {
          // http autorisé en dev (LAN) ; en prod l'app est autonome et l'API en https.
          url: urlDev,
          cleartext: true,
        },
      }
    : {}),
  plugins: {
    SplashScreen: {
      launchShowDuration: 600,
      backgroundColor: '#ee5a44',
      showSpinner: false,
    },
  },
};

export default config;
