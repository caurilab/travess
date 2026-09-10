# Travess mobile (Capacitor)

Enrobage natif **iOS / Android** qui réutilise le bundle web de `travess-web`
(principes n°8/9 : on n'écrit pas une seconde app). L'écran, la logique et les
appels API sont ceux du web ; Capacitor ajoute la couche native (caméra pour le
scan documentaire, splash, barre de statut, stockage).

> Pourquoi pas Expo ? **Expo, c'est React Native.** Ici l'app EST le site web
> empaqueté ; il n'y a pas de composants React Native, donc pas d'Expo Go. Le
> mode « live-reload sur le téléphone » ci-dessous joue le même rôle qu'Expo Go.

## Prérequis

- `pnpm install` à la racine (Capacitor est déjà déclaré ici).
- iOS : macOS + Xcode + CocoaPods (`pod`). Android : Android Studio + un SDK.
- L'API Travess qui tourne (`travess-api`), joignable depuis l'appareil.

## Le bundle

`pnpm bundler` construit `travess-web` avec l'origine API injectée
(`VITE_API_ORIGINE`) et copie le résultat dans `www/`. `pnpm sync` fait ça puis
`cap sync` (met à jour les plateformes natives).

L'origine API par défaut est `http://localhost:8000` (le simulateur iOS partage
le `localhost` du Mac). Pour un **appareil réel**, il faut l'IP du Mac sur le
Wi-Fi :

```bash
TRAVESS_API_ORIGINE=http://192.168.1.20:8000 pnpm sync
```

## Tester dans le simulateur iOS (le plus simple)

```bash
pnpm sync
npx cap run ios
```

Choisir un iPhone simulé. L'API sur `localhost:8000` est jointe directement.

## Tester sur TON iPhone (appareil réel)

Deux approches.

### A. Live-reload par le Wi-Fi (équivalent Expo Go)

Le téléphone charge le bundle **servi par ton Mac** et se recharge à chaque
changement. On n'installe l'app qu'une fois.

1. Mac et iPhone sur le même Wi-Fi. Récupère l'IP du Mac :
   `ipconfig getifaddr en0`.
2. Lance le serveur web en écoute réseau : dans `travess-web`,
   `pnpm dev --host` (Vite sert alors sur `http://<IP>:5180`).
3. Lance l'API en écoute réseau : `php artisan serve --host 0.0.0.0 --port 8000`.
4. Ici :
   ```bash
   CAP_SERVER_URL=http://<IP-du-Mac>:5180 TRAVESS_API_ORIGINE=http://<IP-du-Mac>:8000 pnpm sync
   npx cap open ios
   ```
5. Dans Xcode : sélectionne ton iPhone branché en USB, choisis ton équipe de
   signature (ton Apple ID gratuit suffit pour ton propre appareil), puis ▶︎ Run.
6. Sur l'iPhone : Réglages ▸ Général ▸ VPN et gestion de l'appareil ▸ fais
   confiance à ton profil développeur.

Ensuite, tes changements JS/CSS apparaissent sans rebuild.

### B. App autonome (bundle embarqué)

```bash
TRAVESS_API_ORIGINE=https://api.travess.ci pnpm sync   # une API joignable partout
npx cap open ios     # Xcode ▸ ton iPhone ▸ Run
```

L'app embarque le bundle : elle n'a plus besoin du Mac, seulement de l'API.

## Android sur ton téléphone (sans compte payant)

Téléphone en mode développeur + débogage USB, puis :

```bash
TRAVESS_API_ORIGINE=http://<IP-du-Mac>:8000 pnpm sync
npx cap run android
```

## iOS : accès HTTP en dev

En dev l'API est en clair (`http://`). `Info.plist` autorise le trafic local
(`NSAllowsLocalNetworking`). En production, l'API passe en `https://` et cette
exception ne s'applique pas.
