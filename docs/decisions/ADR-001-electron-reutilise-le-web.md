# ADR-001 — Le desktop réutilise le bundle web (Electron)

## Contexte
Travess doit livrer une application desktop. Deux options : (a) empaqueter le front web React existant dans une fenêtre Electron, ou (b) redévelopper une application desktop distincte.

## Décision
Electron **charge le bundle React de `travess-web`** empaqueté localement, et ajoute une couche native. Pas de redéveloppement. Le desktop = le web + des capacités que le navigateur ne peut pas offrir : mode hors-ligne réel (SQLite local + file de synchronisation), notifications système, impression directe, scan USB, raccourcis clavier.

## Alternatives écartées
- **Redéveloppement desktop distinct** : coût élevé, duplication de l'interface, deux bases à maintenir. Rejeté.
- **Coquille pointant vers une URL distante** : ne fonctionne pas hors-ligne, ne justifie pas l'app desktop. Rejeté.

## Conséquences
- Le desktop dépend d'un web stable → il arrive tard dans le séquencement (Lot 8).
- La justification du desktop est le **hors-ligne**, pas l'interface : marché où la connexion est instable, agent qui doit continuer à travailler.
- À l'inverse, le **mobile reste natif (React Native)** et distinct : ses usages (photo, scan, géoloc, push) sont d'un autre métier que le web réduit.

## Date
2026-09-08
