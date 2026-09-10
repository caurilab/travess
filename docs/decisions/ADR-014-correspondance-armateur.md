# ADR-014 — Correspondance armateur : domaine dédié, réutilisant l'infra Messagerie

## Contexte
Le lot F7 introduit la **correspondance armateur** : le transitaire tient, par dossier, un fil de messages et adresse des demandes à l'armateur (relance surestaries, réclamation, demande de BL/DO). Deux besoins se distinguent :

- un **objet métier** — le fil de messages d'un dossier, sa valeur probante, ses statuts d'envoi ;
- un **transport** — l'acheminement effectif (e-mail au premier lot ; WhatsApp/SMS ensuite), déjà isolé dans le domaine `Messagerie` (contrats `ExpediteurMessage`, adaptateurs factices/réels ; livré au Lot 7.2a/7.2b).

La question était de savoir si la correspondance devait **étendre `Messagerie`** ou constituer un **domaine propre**. `Messagerie` est, par construction (principe n°9), l'infrastructure d'acheminement isolable derrière ses contrats — y loger un agrégat métier rattaché au dossier mêlerait deux responsabilités et lierait le métier au transport. Par ailleurs, la table `messages` porte des données probantes (à qui, quand, quoi) et des données personnelles tierces (l'adresse de l'armateur), ce qui appelle un cadrage tenant et sécurité explicite.

## Décision

1. **Nouveau domaine `App\Domains\Correspondance`** (ADR-002 : un dossier de domaine par domaine métier), distinct de `Messagerie` qui reste l'infra de transport isolable (principe n°9). Table **`messages`** = fil de correspondance d'un dossier : `Message` (RLS + FK composites tenant, ADR-004), `MessagePolicy`, `MessageController`, Actions `CreerEtEnvoyerMessage` et `GenererBrouillon`, job `EnvoyerMessageCorrespondance`, enums `DirectionMessage`/`StatutMessage`/`TypeDemande`.

2. **Réutilisation de l'infra Messagerie sans la modifier** : l'envoi passe par un gabarit `correspondance_libre` (objet + corps composés par l'agent, primitives uniquement) et un mailable `CorrespondanceMail`, acheminés par la `FabriqueExpediteur`/`ExpediteurMessage` existants. **Aucune modification du contrat `ExpediteurMessage`** : la correspondance est un nouvel appelant, pas une évolution de l'infra.

3. **Snapshot du destinataire sur chaque message** (`messages.destinataire_adresse`) : l'adresse réellement employée est **figée** sur le message (valeur probante, immuable même si le contact armateur change ensuite). En complément, ajout de `armateurs.email` — destinataire **de carnet** (PII tierce, non chiffrée, mais **sous la RLS** de `armateurs`, table déjà scopée par tenant) qui alimente le destinataire par défaut. La colonne du message reste la source probante ; le carnet n'est qu'un défaut.

4. **Envoi asynchrone** (principe n°4) : l'Action crée le message en statut `en_file` puis dispatche `EnvoyerMessageCorrespondance` (`JobTenantScoped`, ADR-011) `afterCommit` ; l'API répond **202** sans attendre l'externe. L'humain déclenche explicitement l'envoi, sur un brouillon pré-rempli qu'il valide (principe n°5).

5. **Décisions de sécurité (correctifs d'audit intégrés au lot)** :
   - **Anti-exfiltration / anti-usurpation (B1)** : quand un **armateur est désigné**, l'envoi va **exclusivement** à son e-mail de carnet (une adresse libre fournie est ignorée) ; une **adresse libre** n'est admise qu'en l'absence d'armateur et **réservée au gérant**. On ne peut pas détourner une demande armateur vers une adresse arbitraire.
   - **Canal e-mail uniquement au premier lot** (validation e-mail + canal e-mail seul) ; WhatsApp/SMS restent **différés** via l'`ExpediteurDiffere` de Messagerie (le statut fournisseur `en_file` est conservé sans marquer l'envoi abouti).
   - **`throttle:20,1`** sur l'endpoint d'envoi (C1).
   - **Idempotence anti double-envoi (C2)** : le job prend un verrou d'état par une **transition atomique `en_file → en_cours`** (UPDATE conditionnel) ; si zéro ligne bascule, un autre worker ou un rejeu s'en charge → arrêt avant tout appel réseau.
   - **Robustesse (AC1)** : le verrou `en_cours` est committé **avant** l'appel réseau ; un `failed()` rebascule **`en_cours → échec`** (avec message d'erreur) si l'envoi lève une exception — plus de message fantôme figé, non rejouable. `failed()` s'exécutant hors contexte, le tenant y est rétabli explicitement.
   - **Audit** à la **mise en file** (`correspondance.mise_en_file`) et à l'**aboutissement réel** (`correspondance.aboutie`, tracé par le job) ; l'adresse en clair n'est jamais journalisée.

6. **`messages` est un enfant de l'agrégat dossier** dans `MigrerProprieteDossier` (ADR-013) : lors d'une migration de propriété, le fil **suit le dossier** (re-tenant), il n'est **jamais orphelin**. Il est couvert par le test de complétude piloté par le schéma.

## Alternatives écartées
- **Étendre le domaine `Messagerie`** avec l'agrégat `messages` : mêle le métier (fil probant rattaché au dossier) et le transport (acheminement isolable). Rejeté — `Messagerie` reste l'infra derrière ses contrats (principe n°9), la correspondance est un domaine métier appelant.
- **Modifier le contrat `ExpediteurMessage`** pour porter la notion de correspondance : couple l'infra de transport à un cas d'usage. Rejeté au profit d'un simple gabarit `correspondance_libre` + mailable, sans toucher au contrat.
- **Résoudre l'adresse armateur à l'envoi** (pas de snapshot) : la valeur probante dériverait si le contact change. Rejeté — l'adresse est figée sur le message.
- **Envoi synchrone** : viole le principe n°4 et expose l'agent à la latence/panne du fournisseur. Rejeté (asynchrone en file, réponse 202).
- **Adresse libre ouverte à tous les rôles** : ouvre une voie d'exfiltration. Rejeté — adresse libre réservée au gérant et seulement hors armateur désigné.

## Conséquences
- Tout nouveau canal réel (WhatsApp/SMS) = un adaptateur Messagerie branché derrière `ExpediteurMessage`, sans toucher au domaine Correspondance.
- La suite d'étanchéité inter-tenant s'étend à `messages` (RLS + FK composites) ; le test de complétude de la migration de propriété (ADR-013) couvre désormais `messages`.
- **Périmètre reporté** (hors lot F7) :
  - **entrant IMAP + matching** d'un message reçu à un dossier/armateur (la colonne `direction` et `destinataire_adresse` prévoient déjà l'entrant, mais aucun ingest n'est branché) ;
  - **WhatsApp/SMS réels** (différés tant que les fournisseurs agréés ne sont pas branchés) ;
  - **pièces jointes** ;
  - **relance automatique** d'un message en échec (aujourd'hui : recomposition manuelle par l'agent) ;
  - **recherche plein-texte** du fil ;
  - **pagination** du fil au-delà de 25 messages.

## Date
2026-09-10
