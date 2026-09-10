<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tracking conteneur (docs/08 économie, docs/09 contrat JSONCargo)
|--------------------------------------------------------------------------
|
| Fournisseur isolé derrière l'interface FournisseurTracking (principe n°9).
| « factice » (déterministe, sans réseau ni clé) par défaut : tout le pipeline
| est testable sans JSONCargo. La clé du fournisseur réel vit en .env, jamais
| dans le dépôt, jamais journalisée, jamais recopiée dans un snapshot.
|
*/

return [
    // factice (déterministe, dev/tests) | jsoncargo (réel, reporté : HTTPS +
    // mapping à confirmer avant d'envoyer la clé).
    'driver' => env('TRACKING_DRIVER', 'factice'),

    // Base URL du fournisseur réel — à CONFIRMER en HTTPS avant d'envoyer la clé
    // (la doc JSONCargo la montre en HTTP). Clé mutualisée dans .env, jamais dans
    // le dépôt, jamais journalisée, jamais recopiée dans un snapshot.
    'base_url' => env('JSONCARGO_BASE_URL'),
    'api_key' => env('JSONCARGO_API_KEY'),

    // Plafond de sécurité (fraction du quota mutualisé). Au-delà de la coupure :
    // polling auto arrêté, bascule IMAP/manuel. Alerte interne avant.
    'plafond_coupure' => (float) env('TRACKING_PLAFOND_COUPURE', 0.90),
    'plafond_alerte' => (float) env('TRACKING_PLAFOND_ALERTE', 0.75),

    // TTL du cache des stats de quota (l'appel /stats est lui-même facturé).
    // 0 en test pour refléter immédiatement le quota simulé.
    'cache_stats_ttl' => (int) env('TRACKING_CACHE_STATS_TTL', 300),

    // Fréquences de poll en JOURS (docs/08 §2.2), paramétrables sans redéploiement.
    'seuil_approche_jours' => (int) env('TRACKING_SEUIL_APPROCHE_JOURS', 3),
    'frequences' => [
        'en_mer' => 7,    // en pleine mer : hebdomadaire
        'approche' => 1,  // à l'approche / fraîchement déchargé : quotidien
        'franchise' => 1, // fenêtre de franchise (surestaries/détention) : quotidien
        'enleve' => 30,   // enlevé / livré hors franchise : rare
        // rendu : aucun poll (géré en code, prochain_poll_prevu = null)
    ],

    // Table container_status (brut JSONCargo, en minuscules) → phase, à enrichir
    // en observant les valeurs réelles (docs/09 §4). Ce qui n'est pas listé passe
    // par l'heuristique par mots-clés, puis défaut prudent « en_mer ».
    // Valeurs déjà observées (MSC) :
    'mapping_phases' => [
        'export loaded on vessel' => 'en_mer',
    ],
];
