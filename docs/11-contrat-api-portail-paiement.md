# Travess — Contrat d'API (portail client & paiement Mobile Money)

> API exposée à la surface **portail client**. Périmètre volontairement restreint : un client ne voit que ses dossiers, ne fait que consulter, télécharger et payer. Auth séparée du back-office agent.

---

## 1. Conventions & sécurité

- Base : `/api/v1/portail`
- Auth client dédiée (compte `role=client` rattaché à un `client` d'un tenant).
- **Cloisonnement strict** : un client n'accède qu'aux dossiers de son `client_id`, dans son `tenant_id`. Aucune route ne renvoie de donnée d'un autre client.
- Pas d'accès aux finances internes du tenant (marges, autres dossiers, autres clients).

## 2. Session client
```
POST   /portail/auth/login
POST   /portail/auth/logout
GET    /portail/auth/me            → client, tenant (marque), préférences notif
```

## 3. Dossiers du client
```
GET    /portail/dossiers           → liste (lecture) : référence, sens, statut, avancement
GET    /portail/dossiers/{id}      → détail lecture : étapes visibles, conteneurs, livraison
GET    /portail/dossiers/{id}/suivi→ suivi livraison (position camion, étape en cours)
```
> Les données exposées sont filtrées : le client voit l'avancement et ce qui le concerne, pas les coulisses (barèmes, marges, communication interne).

## 4. Documents du client
```
GET    /portail/documents          → documents partagés avec le client
GET    /portail/documents/{id}     → téléchargement (URL signée, durée limitée)
```

## 5. Facturation & paiement Mobile Money

### 5.1 Ce qui est dû
```
GET    /portail/paiements/dus      → charges + honoraires à payer, par dossier
GET    /portail/paiements/historique → paiements passés + reçus
```

### 5.2 Initier un paiement
```
POST   /portail/paiements
       body: { cible: charge|honoraire, cible_id, operateur: orange|mtn|wave, telephone }
       → { paiement_id, statut: initié, instructions }
```

### 5.3 Suivi & callback
```
GET    /portail/paiements/{id}     → statut (initié / en_attente / réussi / échoué)
POST   /webhooks/paiements/{agregateur}   → callback agrégateur (hors auth client, signé)
```

### 5.4 Reçu
```
GET    /portail/paiements/{id}/recu → PDF du reçu (après succès)
```

## 6. Notifications client
```
GET    /portail/notifications/preferences   → canaux (whatsapp, email)
PATCH  /portail/notifications/preferences
```

## 7. Flux de paiement (résumé)

1. Le client choisit ce qu'il paie et son opérateur.
2. Travess initie la transaction via l'**agrégateur agréé** → réponse « en attente », le client valide sur son téléphone (USSD / push opérateur).
3. L'agrégateur notifie Travess via **webhook signé**.
4. Travess passe le paiement à `réussi`, rapproche l'encaissement du dossier, prélève sa **commission**, génère le **reçu**, notifie le client (WhatsApp/email) et l'agent.
5. En cas d'échec ou de délai, statut `échoué` et possibilité de réessayer.

## 8. Règles & conformité

- **Idempotence** obligatoire sur l'initiation et le webhook (une transaction ne s'applique qu'une fois).
- **Réconciliation** : tout paiement reçu est rapproché d'une charge/honoraire ; les écarts sont signalés.
- **Webhook signé** : vérification de signature de l'agrégateur ; rejet sinon.
- **Cadre réglementaire** : l'encaissement passe par un agrégateur licencié BCEAO ; la commission Travess s'inscrit dans ce partenariat. Volet à valider avec un **conseil juridique local** avant mise en production (voir `02` et `05`).
- **Traçabilité** : chaque paiement et chaque changement de statut est audité.

## 9. Ce que le portail n'expose jamais

- Les autres clients ou dossiers du tenant.
- Les barèmes, marges, honoraires d'autres dossiers.
- La communication interne ou avec l'armateur.
- Les paramètres du tenant ou de la plateforme.
