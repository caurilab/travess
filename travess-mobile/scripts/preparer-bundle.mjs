// Prépare le bundle web pour l'enrobage natif : (1) build de travess-web avec
// l'origine API injectée (VITE_API_ORIGINE), (2) copie de dist/ vers www/.
// Le même bundle sert le web, le desktop et le mobile (principes n°8/9).

import { execSync } from 'node:child_process';
import { cpSync, existsSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const ici = dirname(fileURLToPath(import.meta.url));
const racineMobile = resolve(ici, '..');
const racineWeb = resolve(racineMobile, '..', 'travess-web');

// Origine de l'API embarquée dans le bundle. Par défaut : le Mac hôte, joignable
// depuis le simulateur iOS (localhost partagé) — à surcharger pour un appareil
// réel (IP LAN du Mac) ou la prod (https://api.travess.ci).
const origineApi = process.env.TRAVESS_API_ORIGINE ?? 'http://localhost:8000';

console.log(`[mobile] build web · VITE_API_ORIGINE=${origineApi}`);
execSync('pnpm build', {
  cwd: racineWeb,
  stdio: 'inherit',
  env: { ...process.env, VITE_API_ORIGINE: origineApi },
});

const dist = resolve(racineWeb, 'dist');
const www = resolve(racineMobile, 'www');
if (!existsSync(dist)) {
  throw new Error(`Bundle introuvable : ${dist}`);
}
rmSync(www, { recursive: true, force: true });
cpSync(dist, www, { recursive: true });
console.log(`[mobile] bundle copié → ${www}`);
