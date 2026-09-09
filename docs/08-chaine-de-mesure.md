# Travess — Chaîne de mesure & économie du tracking

> Comment Travess consomme JSONCargo sans gaspiller le quota, et comment la consommation est mesurée par tenant. C'est le document qui protège la marge.

---

## 1. Le modèle de comptage JSONCargo

- **1 requête = 1 appel facturé**, y compris pour un conteneur déjà suivi. Pas de push : c'est du **polling**.
- Exemple officiel de l'éditeur : 500 conteneurs interrogés 3 fois/semaine ≈ **6 495 appels/mois**.

### Barème (prix pleins, après période d'essai)

| Plan | Prix/mois | Appels inclus | Coût unitaire au-delà |
|---|---|---|---|
| Mariner | 99 € | 1 000 | 0,099 € |
| Navigator | 199 € | 2 500 | 0,080 € |
| Admiral | 349 € | 5 000 | 0,070 € |

> Les prix vitrine (9 / 22 / 39 €) sont des tarifs d'essai (7 j Mariner, 14 j Navigator/Admiral) qui basculent automatiquement au tarif plein. Sans engagement, résiliable.

## 2. La règle d'or : ne jamais poller en boucle aveugle

Un scheduler naïf qui interroge tous les conteneurs à intervalle fixe brûle le quota et n'apporte aucune valeur en pleine mer (rien ne change pendant des jours). Travess poll **intelligemment**.

### 2.1 Ne poller que les conteneurs actifs
Réutilise la logique `actif` des franchises (`surestariesActif` / `detentionActif`). Un conteneur `rendu` ou un dossier clôturé n'est plus interrogé.

### 2.2 Fréquence variable selon la phase
| Phase du conteneur | Fréquence de poll |
|---|---|
| En pleine mer (loin du port) | Hebdomadaire |
| À l'approche du port de déchargement | Quotidienne |
| Pendant la fenêtre de franchise (surestaries/détention) | Quotidienne |
| Enlevé, hors franchise | Rare / à la demande |
| Rendu / clôturé | Aucun |

### 2.3 Le champ `prochain_poll_prevu`
Chaque `suivi_tracking` porte un `prochain_poll_prevu`, **calculé dynamiquement** à partir de `container_status` et `eta_final_destination`. Le scheduler ne réveille que les conteneurs dont l'échéance est atteinte. C'est le cœur de l'économie d'appels.

## 3. Le plafond de sécurité (obligatoire)

- L'endpoint de stats JSONCargo (`/api_key/stats`) est interrogé régulièrement : `requests_made`, `requests_available`.
- À **~90 % du quota mensuel consommé**, Travess **coupe le polling automatique** et bascule sur le **fallback IMAP** + saisie manuelle.
- Objectif : ne jamais cramer le quota ni tomber en dépassement à cause d'un bug de scheduler ou d'un pic de charge.
- Une alerte interne prévient l'éditeur avant la coupure (ex. à 75 %).

## 4. Mesure par tenant

JSONCargo facture globalement (un seul abonnement mutualisé), mais Travess **journalise la consommation par tenant** dans `consommation_service` :
- combien d'appels chaque tenant a déclenché ;
- pour dimensionner les paliers de prix ;
- pour facturer le tracking en option sur les plans qui ne l'incluent pas ;
- pour repérer un tenant anormalement gourmand.

## 5. L'économie réelle

- Coût réel par conteneur sur son cycle de vie : **quelques centimes d'euro** (une poignée d'appels bien placés, pas un polling permanent).
- Vendu comme option ou inclus dans les plans supérieurs → **marge nette confortable**.
- Un seul abonnement mutualisé couvre de nombreux tenants tant que le scheduler reste discipliné.

## 6. Démarrage recommandé

1. Souscrire **Navigator** (199 €, 2 500 appels).
2. Instrumenter la consommation dès le premier jour (`consommation_service` + lecture `/api_key/stats`).
3. Observer la consommation réelle sur quelques semaines.
4. Ajuster le plan (monter vers Admiral si besoin) une fois la volumétrie connue.

## 7. Points de vigilance techniques

- **Grimaldi et armateurs non couverts** : jamais de poll (économie), fallback direct.
- **Identification navire par IMO/MMSI** systématique (le nom seul est ambigu — plusieurs navires peuvent le partager).
- **HTTP vs HTTPS** : la base URL documentée en HTTP doit être confirmée en HTTPS avant d'envoyer la clé.
- **Idempotence** : un même snapshot ne doit pas déclencher deux fois les mêmes alertes.
