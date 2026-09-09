<?php

declare(strict_types=1);

use App\Domains\Documents\Enums\TypeDocument;

/*
|--------------------------------------------------------------------------
| Ingestion documentaire par IA
|--------------------------------------------------------------------------
|
| Le fournisseur concret est isolé derrière l'adaptateur ExtracteurDocument
| (principe n°9). « factice » permet le dev/les tests sans clé. La clé API
| vit dans .env (jamais dans le dépôt, jamais journalisée).
|
*/

return [
    // 'factice' (déterministe, sans réseau) | 'laravel_ai' (Claude via laravel/ai)
    'driver' => env('IA_DRIVER', 'factice'),

    'provider' => env('IA_PROVIDER', 'anthropic'),
    'modele' => env('IA_MODELE', 'claude-sonnet-5'),

    // Disque objet où sont stockés les documents (lecture côté serveur, base64
    // inline vers le fournisseur — jamais d'URL publique).
    'disque' => env('IA_DISQUE', env('FILESYSTEM_DISK', 'local')),

    // Confidentialité : l'ingestion IA n'est active que si le tenant a activé
    // l'option (tenant.parametres->ia_activee).
    // NB : la non-rétention côté fournisseur dépend de la configuration du compte
    // Anthropic (zero data retention) ; elle n'est pas imposée par le code. À
    // verrouiller avec l'intégrateur externe avant prod (cf. CLAUDE.md).
    'opt_in_parametre' => 'ia_activee',

    // Unité de décompte de consommation (par document extrait).
    'unite_consommation' => 'document',
    'unites_par_document' => 1,

    // Schémas d'extraction par type de document (paramétrable, sans redéploiement
    // de logique). Chaque champ : nom => type indicatif.
    'schemas' => [
        // NB : « armateur » est extrait sous forme de NOM (indicatif, affiché à
        // l'humain). L'application au dossier exige « armateur_id » (annuaire),
        // fourni à la validation. Le rapprochement nom → id automatique est une
        // dette assumée (voir docs/etat-du-projet.md).
        TypeDocument::Bl->value => [
            'numero_bl' => 'string',
            'armateur' => 'string',
            'navire_nom' => 'string',
            'navire_imo' => 'string',
            'conteneurs' => 'liste', // [{numero, type}]
            'port_chargement' => 'string',
            'port_dechargement' => 'string',
            'date_arrivee_prevue' => 'date',
        ],
        TypeDocument::FactureCharges->value => [
            'emetteur' => 'string',
            'numero_facture' => 'string',
            'devise' => 'string',
            'lignes' => 'liste', // [{libelle, montant}]
            'total' => 'number',
            'date' => 'date',
        ],
        TypeDocument::Do->value => [
            'reference' => 'string',
            'date' => 'date',
        ],
        TypeDocument::DeclarationDouane->value => [
            'reference' => 'string',
            'regime' => 'string',
            'valeur_declaree' => 'number',
            'date' => 'date',
        ],
        TypeDocument::BonLivraison->value => [
            'reference' => 'string',
            'date' => 'date',
        ],
        TypeDocument::Autre->value => [],
    ],
];
