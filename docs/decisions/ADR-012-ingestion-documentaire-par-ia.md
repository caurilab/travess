# ADR-012 — Ingestion documentaire par IA

## Contexte
Le Lot 5 introduit la lecture assistée des documents (BL en priorité) pour pré-remplir un dossier : numéro de BL, armateur, navire (IMO), conteneurs, ports, date d'arrivée. Trois principes projet cadrent la conception :
- **Principe n°5** — l'IA propose, l'humain valide : aucune écriture automatique au dossier depuis l'extraction.
- **Principe n°4** — tout traitement lourd passe en file : l'appel au fournisseur ne bloque jamais la requête HTTP.
- **Principe n°9** — chaque fournisseur externe est isolé derrière un adaptateur dans son domaine.

L'enjeu est de livrer un pipeline d'extraction complet, testable de bout en bout **sans clé API**, tout en gardant le fournisseur d'IA interchangeable et l'écriture au dossier sous contrôle humain.

## Décision

### Contrat et adaptateurs (principe n°9)
- Contrat `ExtracteurDocument` (`app/Domains/Ingestion/Contracts`) avec deux adaptateurs :
  - `ExtracteurFactice` — déterministe, sans réseau, **driver par défaut** ; permet d'exercer tout le pipeline (Action → job → validation → application) sans clé API.
  - `ExtracteurLaravelAi` — Claude via `laravel/ai` (v0.11.2), sortie structurée `{valeur, confiance, zone_source}` par champ, document transmis en **base64 inline** (jamais d'URL publique).
- Le driver est choisi par `config('ia.driver')` (`IA_DRIVER`, défaut `factice`).

### DTO neutres et schémas paramétrables
- DTO indépendants du fournisseur : `DocumentAExtraire`, `SchemaExtraction`, `ChampExtrait`, `ResultatExtraction`.
- Schémas d'extraction par type de document dans `config/ia.php`, exposés via `RegistreSchemas` — ajout ou ajustement d'un schéma sans redéploiement de logique.

### Pipeline
- **Action `LancerExtraction`** : opt-in tenant (`tenant.parametres->ia_activee`), **réservation atomique du quota** (plafond dur), verrou consultatif `pg_advisory_xact_lock` anti double-lancement, idempotence sur extraction `en_file`/`reussi` non validée, dispatch `afterCommit`.
- **Job `ExtraireDocument`** (`JobTenantScoped`) : idempotent au rejeu, re-vérifie l'opt-in, `report($e)` pour l'observabilité, audit du résultat, libère l'unité de consommation en cas d'échec.
- **Action `ValiderExtraction`** : validation humaine sous verrou `lockForUpdate`, applique au dossier via un `ApplicateurExtraction` par type (`FabriqueApplicateur`). `ApplicateurBl` crée le BL et ses conteneurs.

### Décompte de consommation tenant-scopé
- Service `DecompteConsommationIa` : réservation / libération / enregistrement du coût. L'incrément est **conditionnel et atomique**, borné par `quota_ia_mensuel` — le plafond ne peut être dépassé même en concurrence.

### Surface API
- `POST /documents/{document}/extraction` — lance une extraction.
- `GET /documents/{document}/extraction` — consulte le résultat.
- `POST /extractions/{extraction}/validation` — validation humaine et application au dossier.
- Autorisation par `DocumentPolicy::extraire` (rôles en écriture).

## Alternatives écartées
- **Écriture automatique au dossier depuis l'extraction** : viole le principe n°5. Rejetée au profit d'une validation humaine explicite.
- **Appel synchrone au fournisseur dans la requête HTTP** : viole le principe n°4 et expose la latence/les pannes du fournisseur. Rejeté au profit du job en file.
- **Couplage au SDK du fournisseur dans le domaine** : viole le principe n°9. Rejeté au profit du contrat `ExtracteurDocument` et de DTO neutres.
- **Pipeline non testable sans clé** : rejeté ; l'adaptateur factice déterministe est le défaut et couvre le pipeline complet.

## Conséquences

### Acquis
- Pipeline d'ingestion complet, exerçable de bout en bout sans clé API (adaptateur factice par défaut).
- Fournisseur d'IA interchangeable par simple bascule de `config('ia.driver')`.
- Quota tenant respecté de façon atomique ; opt-in requis ; tout tracé en audit.

### Dette technique explicitement assumée
- **Résolution armateur** : l'IA extrait le **nom** d'armateur (indicatif, affiché à l'humain) ; l'application au dossier exige `armateur_id` (annuaire), fourni par l'humain à la validation. Le rapprochement automatique nom → id reste à faire.
- **Confidentialité** : la non-rétention côté fournisseur dépend de la configuration du compte Anthropic (zero data retention) — elle n'est **pas** imposée par le code. À verrouiller avec l'intégrateur externe avant prod.
- **Schéma « liste » générique** → array de chaînes ; l'extraction structurée des conteneurs (objets `{numero, type}`) reste à affiner.
- **Reprise sur échec** : le job n'a pas de retry configuré (il attrape lui-même `Throwable`) ; la reprise est manuelle. Le motif d'échec n'est pas persisté (pas de colonne erreur) — évolution possible.
- **Identifiant de modèle** (`IA_MODELE=claude-sonnet-5`) et **fournisseur** à confirmer avec l'intégrateur externe.

## Date
2026-09-10
