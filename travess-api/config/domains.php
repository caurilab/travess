<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Domaines métier Travess
|--------------------------------------------------------------------------
|
| L'API est découpée par domaine métier (et non par couche technique).
| Chaque domaine vit sous app/Domains/<Domaine>/ et peut exposer un fichier
| de routes routes/domains/<domaine-kebab>.php, chargé automatiquement sous
| le préfixe /api/v1 (voir routes/api.php).
|
*/

return [
    'list' => [
        'Tenancy',
        'Identity',
        'Dossiers',
        'Conteneurs',
        'Surestaries',
        'Finances',
        'Transport',
        'Tracking',
        'Ingestion',
        'Portail',
        'Paiements',
        'Messagerie',
    ],
];
