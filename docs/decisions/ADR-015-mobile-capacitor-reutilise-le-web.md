# ADR-015 — Mobile : Capacitor réutilise le bundle web (amende le principe n°8)

## Statut
Accepté. Amende le **principe n°8** (« le desktop réutilise le web ; le mobile
est natif et distinct ») et le point « Mobile : React Native (natif, distinct) »
de la stack figée. ADR-001 (Electron réutilise le web) reste valable et inchangé.

## Contexte
La stack initiale prévoyait un mobile **React Native**, séparé du web. À l'usage,
ce choix impose une seconde base de code : réécrire écrans, navigation, appels
API et cœur d'affichage déjà livrés et éprouvés côté `travess-web` (React 19).
Or le besoin mobile réel de Travess est d'abord **le même produit, en poche** —
consulter un dossier, suivre un conteneur, recevoir une alerte — plus un usage
natif ciblé : **scanner un document avec la caméra**. Rien de tout cela n'exige
une interface native sur mesure ni une seconde base React Native.

La question posée par l'équipe : puisque l'app est déjà un React, ne peut-on pas
empaqueter **le même bundle** dans une coquille native, comme le desktop
Electron, la caméra en plus ?

## Décision

1. **Le mobile réutilise le bundle web via Capacitor**, exactement comme le
   desktop réutilise le web via Electron (ADR-001). Une seule base d'UI
   (`travess-web`), trois surfaces (web, desktop, mobile). Le paquet
   `travess-mobile` n'ajoute que la configuration native et les appels aux
   plugins ; il ne réécrit aucun écran.

2. **Le principe n°8 est reformulé** : « Le desktop **et le mobile** réutilisent
   le bundle web ; chacun ajoute sa couche native (Electron pour le desktop,
   Capacitor pour le mobile). » On abandonne React Native. Le principe n°9
   (chaque provider externe isolé derrière un adaptateur) est **renforcé** : les
   capacités natives (caméra, stockage) restent derrière la même logique
   d'isolation.

3. **L'origine de l'API est résolue par surface**, sans dupliquer le client :
   - web : base **relative** `/api/v1` (proxy Vite en dev, reverse-proxy en prod) ;
   - desktop : injectée à l'exécution via `window.travessDesktop.apiBase` ;
   - mobile : figée **au build** via `VITE_API_ORIGINE` (l'app native n'a pas de
     proxy ; l'origine réseau de l'API est connue à la compilation).
   Le même `api/client.ts` couvre les trois cas.

4. **Le scan documentaire** (caméra) alimente le pipeline d'ingestion existant
   (ADR-012) : la photo devient un document téléversé, puis extrait par l'IA sous
   validation humaine (principe n°5). Aucune écriture automatique.

5. **Distribution / test** : le simulateur iOS joint l'API sur le `localhost` du
   Mac ; un appareil réel passe par le live-reload Wi-Fi (bundle servi par le
   Mac, équivalent d'un Expo Go) ou par une app autonome à bundle embarqué. Le
   détail opératoire est dans `travess-mobile/README.md`.

## Conséquences

**Positives** — une seule base d'UI à maintenir ; parité fonctionnelle immédiate
entre web et mobile ; le cœur métier partagé (`shared-core`, `shared-types`)
sert les trois surfaces sans effort ; livraison mobile accélérée.

**Négatives / limites** — les interactions très natives (gestes complexes,
performances de rendu extrêmes) restent moins fines qu'en natif pur ; on l'accepte
car le produit est orienté données et formulaires, pas animation temps réel. Les
capacités natives dépendent de la disponibilité d'un plugin Capacitor (caméra,
stockage, notifications sont couverts).

**Expo est écarté** : Expo est l'outillage de React Native. Puisqu'on abandonne
React Native, Expo n'a pas d'objet ici ; le rôle « recharge à chaud sur le
téléphone » qu'on lui associe est tenu par le mode live-reload de Capacitor.
