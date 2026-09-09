<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Messagerie sortante & OTP (onboarding portail)
|--------------------------------------------------------------------------
|
| Fournisseurs isolés derrière des interfaces (principe n°9). « factice » par
| défaut : tout le flux d'onboarding est testable sans clé ni réseau. Les clés
| des fournisseurs réels vivent dans .env, jamais dans le dépôt, jamais en log.
|
*/

return [
    // factice (déterministe, sans réseau) | reel (email réel, whatsapp/sms différés)
    'driver' => env('MESSAGERIE_DRIVER', 'factice'),

    'otp' => [
        'driver' => env('OTP_DRIVER', 'factice'),
        'longueur' => 6,
        'ttl_secondes' => 300,       // 5 min
        'tentatives_max' => 5,
        'verrou_secondes' => 900,    // 15 min
        'envois_max_par_numero_heure' => 5,
        // Code fixe en test/factice (null → dérivé déterministe du contexte).
        'code_factice' => env('OTP_CODE_FACTICE', '123456'),
    ],

    'invitation' => [
        'ttl_heures' => 72,
        'longueur_token_octets' => 32,          // 256 bits
        'emission_max_par_tenant_heure' => 100,
    ],
];
