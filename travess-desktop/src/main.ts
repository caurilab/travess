import { app, BrowserWindow, shell } from 'electron';
import * as path from 'node:path';

/**
 * Processus principal Electron de Travess.
 *
 * Principe n°8 : le desktop RÉUTILISE le bundle web, il ne le réécrit pas.
 *  - en dev (TRAVESS_DESKTOP_DEV=1) : charge le serveur Vite de travess-web
 *    (HMR + proxy /api) ;
 *  - en prod : charge le bundle statique construit (travess-web/dist) et injecte
 *    l'origine absolue de l'API (le proxy Vite n'existe plus) via le preload.
 *
 * La couche native (SQLite hors-ligne, impression, scan, notifications) viendra
 * s'ajouter par le pont contextBridge exposé dans preload.ts — sans toucher le web.
 */

const EST_DEV = process.env.TRAVESS_DESKTOP_DEV === '1';
const URL_DEV = process.env.TRAVESS_WEB_URL ?? 'http://localhost:5180';
const CHEMIN_BUNDLE = path.join(__dirname, '..', '..', 'travess-web', 'dist', 'index.html');

function creerFenetre(): void {
  const fenetre = new BrowserWindow({
    width: 1280,
    height: 860,
    minWidth: 960,
    minHeight: 600,
    title: 'Travess',
    backgroundColor: '#faf7f5',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  if (EST_DEV) {
    void fenetre.loadURL(URL_DEV);
  } else {
    void fenetre.loadFile(CHEMIN_BUNDLE);
  }

  // Les liens externes s'ouvrent dans le navigateur système, jamais dans l'app.
  fenetre.webContents.setWindowOpenHandler(({ url }) => {
    void shell.openExternal(url);

    return { action: 'deny' };
  });
}

void app.whenReady().then(() => {
  creerFenetre();

  // macOS : recrée une fenêtre au clic sur l'icône du dock si aucune n'est ouverte.
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      creerFenetre();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
