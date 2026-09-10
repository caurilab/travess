# Travess — Modèle de données

> Vue logique, indépendante de l'implémentation exacte des migrations. PostgreSQL. Chaque table métier porte `tenant_id`. Les identifiants sont des UUID.

---

## 1. Schéma d'ensemble

```
tenant ──< user
tenant ──< armateur (barèmes, IMAP, préfixes)
tenant ──< client
tenant ──< dossier
   dossier ──< etape
   dossier ──< bl (connaissement)
       bl ──< conteneur
   dossier ──< document
   dossier ──< charge / encaissement / honoraire
   dossier ──< mission_transport ──> chauffeur, camion
   dossier ──< message        (lot ultérieur — Communication/Messagerie)
conteneur ──< suivi_tracking (snapshots JSONCargo)
conteneur ──< franchise (surestaries / détention)
dossier ──< alerte
paiement ──> encaissement / honoraire
```

## 2. Tenancy & identité

### tenant
| Champ | Type | Note |
|---|---|---|
| id | uuid | |
| nom | string | société de transit |
| plan | enum | essentiel / pro / business / sur-mesure |
| statut | enum | actif / suspendu |
| quota_ia_mensuel | int | extractions incluses |
| quota_tracking_mensuel | int | appels inclus (part du tenant) |
| parametres | jsonb | préférences, options |
| created_at… | | |

### user
| id | uuid |
| tenant_id | uuid | FK |
| nom, email | | |
| role | enum | gerant / agent / comptable / chauffeur / client |
| auth… | | passkey / 2FA |
| preferences_notif | jsonb | canaux par type d'événement |

> Le rôle `client` est rattaché à un `client` (donneur d'ordre) et n'accède qu'au portail.

## 3. Référentiels

### armateur
| id | uuid |
| tenant_id | uuid | (barème personnalisable par tenant) |
| nom | string | Maersk, MSC, CMA CGM, Grimaldi… |
| nom_api | string | nom normalisé JSONCargo (MAERSK, CMA_CGM…) ; null si non couvert |
| email | string (nullable) | destinataire de carnet pour la correspondance (PII sous RLS) ; l'adresse effective est figée sur chaque message |
| prefixes | jsonb | préfixes de conteneur connus |
| trackable | bool | false pour Grimaldi & non couverts |
| bareme_surestaries | jsonb | paliers jours → tarif/jour |
| bareme_detention | jsonb | idem |
| imap_config | jsonb (chiffré) | fallback |

### client (donneur d'ordre)
| id | uuid |
| tenant_id | uuid |
| nom, contact | | |
| canaux | jsonb | numéro WhatsApp, email |

## 4. Dossier & workflow

### dossier
| id | uuid |
| tenant_id | uuid |
| reference | string | numéro interne |
| sens | enum | import / export |
| client_id | uuid | FK |
| statut | enum | ouvert / en_cours / bloqué / clôturé |
| motif_blocage | string? | ex. « attente paiement client » |
| agents | jointure | assignation multiple |
| created_at… | | |

### etape
| id | uuid |
| dossier_id | uuid |
| ordre | int |
| libelle | string |
| sla_jours | int | éditable |
| date_prevue | date? |
| date_reelle | date? |
| statut | enum | à_faire / en_cours / fait / en_retard |
| responsable_id | uuid? |

### bl (connaissement)
| id | uuid |
| dossier_id | uuid |
| numero | string |
| armateur_id | uuid |
| navire_nom | string? |
| navire_imo | string? | **clé d'identification navire** |

## 5. Conteneurs & franchises

### conteneur
| id | uuid |
| tenant_id | uuid |
| bl_id | uuid |
| numero | string | validé ISO 6346 |
| type | enum | 20 / 40 / 40HC / reefer… |
| statut | enum | à_traiter / enlevé / livré / rendu |
| source_numero | enum | manuel / import_bol / scan / ia |

### franchise
| id | uuid |
| conteneur_id | uuid |
| type | enum | surestaries / détention |
| date_debut | date | (ex. discharging / sortie port) |
| jours_francs | int |
| date_fin_franchise | date | calculée |
| montant_en_cours | decimal | calculé en continu |
| montant_menacant | decimal | ce qui va tomber |
| actif | bool | (réutilise la logique surestariesActif/detentionActif) |

### suivi_tracking
| id | uuid |
| conteneur_id | uuid |
| source | enum | jsoncargo / imap / manuel |
| snapshot | jsonb | réponse brute normalisée |
| statut_conteneur | string | mappé vers `conteneur.statut` |
| emplacement | string |
| eta_destination | timestamp? |
| navire_nom, navire_imo | | |
| prochain_poll_prevu | timestamp | calculé (scheduler intelligent) |
| captured_at | timestamp |

## 6. Documents & ingestion

### document
| id | uuid |
| tenant_id, dossier_id | uuid |
| type | enum | bl / facture_charges / do / declaration_douane / bon_livraison / autre |
| chemin_stockage | string | objet S3 |
| origine | enum | upload_web / photo_mobile / whatsapp / scan |
| statut_ingestion | enum | none / en_file / extrait / validé |

### extraction_ia
| id | uuid |
| document_id | uuid |
| statut | enum | en_file / réussi / échoué |
| champs | jsonb | {champ: {valeur, confiance, zone_source}} |
| corrections | jsonb | corrections agent (amélioration continue) |
| validé_par | uuid? |
| validé_at | timestamp? |
| cout_unite | decimal | pour la mesure de consommation |

## 7. Finances

### charge (argent sorti)
| id, tenant_id, dossier_id | |
| libelle, montant | |
| avancee_pour_client | bool |

### encaissement (argent entré)
| id, tenant_id, dossier_id | |
| montant, date | |
| rapproche_charge_id | uuid? | rapprochement auto |

### honoraire
| id, tenant_id, dossier_id | |
| montant | |
| numero_facture | string | numérotation légale continue |
| pdf_chemin | string |

### echeance (trésorerie consolidée)
| vue agrégée charges/encaissements/honoraires par date |

## 8. Transport

### mission_transport
| id, tenant_id, dossier_id | |
| chauffeur_id | uuid |
| camion | string |
| statut | enum | prévue / en_route / livrée |
| positions | jsonb / PostGIS | trace géolocalisée |
| lien_suivi_public | string | token lecture seule pour le client |
| bon_livraison_doc_id | uuid? | scan à la remise |

## 9. Alertes & notifications

### alerte
| id, tenant_id, dossier_id, conteneur_id | |
| type | enum | surestaries_j3 / surestaries_j1 / surestaries_j0 / detention_* / sla_depasse / blocage |
| montant_menacant | decimal? |
| statut | enum | ouverte / vue / traitée |
| canaux_envoyes | jsonb |

### notification
| journal des envois (push / whatsapp / email / desktop) avec statut |

### message (correspondance armateur — domaine `Correspondance`, ADR-014)
| id, tenant_id, dossier_id | | fil rattaché au dossier (RLS + FK composites tenant) |
| armateur_id | uuid? | armateur désigné (FK composite tenant) |
| auteur_id | uuid? | agent émetteur (hors schéma composite, nullOnDelete) |
| direction | enum | sortant / entrant (entrant IMAP reporté) |
| type_demande | enum? | relance surestaries / réclamation / demande BL-DO… |
| canal | enum | email (whatsapp/sms différés) |
| destinataire_adresse | string | **snapshot** de l'adresse d'envoi (valeur probante, figée) |
| objet, corps | string / text | composés par l'agent, validés avant envoi (principe n°5) |
| statut | enum | en_file / en_cours / envoyé / échec (transition atomique anti double-envoi) |
| reference_externe, erreur | string? | retour fournisseur / motif d'échec |
| envoye_at, recu_at | timestamp? | |
| meta | jsonb | |

## 10. Paiements (portail)

### paiement
| id, tenant_id, dossier_id, client_id | |
| montant | |
| cible | enum | charge / honoraire |
| operateur | enum | orange / mtn / wave |
| statut | enum | initié / en_attente / réussi / échoué |
| ref_agregateur | string |
| commission_travess | decimal |
| recu_chemin | string |

## 11. Consommation & audit

### consommation_service
| tenant_id | |
| service | enum | tracking / ia / whatsapp |
| periode | mois |
| quantite | int | pour paliers de prix & dépassement |

### audit_log
| tenant_id, user_id | |
| entite, entite_id | |
| action | string |
| avant, apres | jsonb |
| at | timestamp | inaltérable |

## 12. Règles de données

- **Isolation** : `tenant_id` obligatoire et scoping systématique sur toutes les tables ci-dessus (sauf `tenant` lui-même).
- **Navire** : `navire_imo` est la clé fiable ; `navire_nom` est indicatif.
- **Conteneur** : `numero` toujours validé ISO 6346 avant persistance.
- **Franchise** : `date_fin_franchise`, `montant_en_cours`, `montant_menacant`, `prochain_poll_prevu` sont **calculés** (jamais saisis à la main), à partir de `shared-core`.
- **Snapshots tracking** : conservés en `jsonb` pour l'historique et l'analyse (base des données agrégées V2).
