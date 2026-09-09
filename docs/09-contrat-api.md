# Travess — Contrat d'API JSONCargo (tracking)

> Documentation de référence de l'intégration tracking. Source : documentation officielle JSONCargo. À revalider contre la doc en ligne au moment de l'implémentation (versions, base URL, schémas).

---

## 1. Généralités

- **Base URL (documentée)** : `http://api.jsoncargo.com/api/v1/`
  - ⚠️ **HTTP dans les exemples de la doc** — à confirmer en **HTTPS** auprès de l'éditeur avant d'envoyer la clé. Ne jamais transmettre `x-api-key` en clair.
- **Authentification** : header `x-api-key: <clé>`.
- **Format** : JSON.
- **Clé mutualisée** côté `travess-api`, jamais exposée aux clients.

### 1.1 Noms d'armateurs (paramètre `shipping_line`)
Obligatoire, surtout pour les préfixes de conteneur partagés (sinon 404 ou données erronées) :

`MAERSK` · `HAPAG_LLOYD` · `HMM` · `ONE` · `EVERGREEN` · `MSC` · `CMA_CGM` · `COSCO` · `ZIM` · `YANG_MING` · `PIL`

IDs internes correspondants : Maersk 0010, Hapag-Lloyd 0011, HMM 0012, ONE 0013, Evergreen 0014, MSC 0015, CMA CGM 0016, COSCO 0017, ZIM 0018, Yang Ming 0019, PIL 0020.

> **Grimaldi n'est pas au catalogue.** Aucun appel JSONCargo pour Grimaldi → fallback IMAP + manuel.

## 2. Endpoints utilisés par Travess

### E1 — Détails d'un conteneur
```
GET /containers/{tracking_number}?shipping_line={NOM}
```
Champs de réponse (principaux) :
`container_id`, `container_type`, `container_status`, `shipping_line_name`, `shipping_line_id`, `tare`, `shipped_from`, `shipped_from_terminal`, `shipped_to`, `shipped_to_terminal`, `atd_origin`, `eta_final_destination`, `last_location`, `last_location_terminal`, `next_location`, `next_location_terminal`, `atd_last_location`, `eta_next_destination`, `timestamp_of_last_location`, `last_movement_timestamp`, `loading_port`, `discharging_port`, `customs_clearance`, `bill_of_lading`, `last_vessel_name`, `current_vessel_name`, `last_voyage_number`, `current_voyage_number`, `last_updated`.

### E2 — Numéros de conteneur depuis un BL *(inversion de saisie)*
```
GET /containers/bol/{bol_number}?shipping_line={NOM}
```
Réponse : `associated_container_numbers` (array).
**Usage clé** : l'agent saisit BL + armateur → Travess récupère les conteneurs → chacun est contrôlé par `iso6346Valid()` avant persistance.

### E3/E4 — Suivi navire (basic / pro)
```
GET /vessels/{uuid|mmsi|imo}            (basic)
GET /vessels/pro/{uuid|mmsi|imo}        (pro)
```
Le mode **pro** ajoute : `dest_port`, `dep_port`, unlocodes, `atd`, `eta`, timezone.

### E5 — Suivi navire en lot
```
POST /vessels/bulk        (jusqu'à 100 navires)
```

### E6 — Vessel Finder (fiche navire statique)
```
GET /vessels/finder/{name|imo|mmsi}
```
Réponse (voir aussi `10-...` pour la vulgarisation) : `uuid`, `name`, `name_ais`, `mmsi`, `imo`, `eni`, `country_iso`, `country_name`, `callsign`, `type`, `type_specific`, `gross_tonnage`, `deadweight`, `teu`, `liquid_gas`, `length`, `breadth`, `draught_avg`, `draught_max`, `speed_avg`, `speed_max`, `year_built`, `is_navaid`, `home_port`.
> Peut renvoyer **plusieurs navires de même nom** → identifier par **IMO**, jamais par le nom.

### E7 — Vessel Specs · E8 — Port Finder · E9 — Terminal Finder
Données de référence (spécifications navire, ports, terminaux). Usage ponctuel / enrichissement.

### E10 — Statistiques de la clé *(pilotage du plafond)*
```
GET /api_key/stats
```
Réponse : `plan`, `requests_total`, `requests_made`, `requests_available`.
**Usage** : lecture régulière pour le plafond de sécurité (coupure à ~90 %).

## 3. Mapping vers le modèle Travess

| Champ JSONCargo | Cible Travess | Usage |
|---|---|---|
| `container_status` | `conteneur.statut` (via table de correspondance) | statut affiché |
| `eta_final_destination` + `discharging_port` | `franchise.date_debut` (surestaries) | début de franchise |
| `customs_clearance` | recoupe l'étape `dédouanement` | contrôle croisé |
| `current_vessel_name` | `bl.navire_nom` | affichage |
| (IMO obtenu via E6) | `bl.navire_imo` | clé d'identification |
| `associated_container_numbers` (E2) | création `conteneur` (après ISO 6346) | import depuis BL |

## 4. Table de correspondance des statuts (à compléter)

`container_status` (JSONCargo) → `conteneur.statut` (Travess) : à établir précisément à l'implémentation en observant les valeurs réelles renvoyées. Prévoir un statut `inconnu` par défaut et journaliser toute valeur non mappée.

## 5. Règles d'intégration (rappel)

- Appels **en queue**, jamais synchrones dans une requête agent.
- Scheduler intelligent + `prochain_poll_prevu` (voir `08`).
- Plafond de sécurité via E10 + bascule IMAP.
- Snapshots stockés en `jsonb` (`suivi_tracking.snapshot`).
- Idempotence des alertes.
- Provider isolé dans le domaine `Tracking` : un changement de fournisseur de tracking ne doit toucher qu'une couche d'adaptation.
