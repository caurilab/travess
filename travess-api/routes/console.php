<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
| Tâches planifiées.
|
| Rafraîchissement quotidien des surestaries (recalcul des franchises + génération
| des alertes de seuil), tôt le matin pour que les alertes J0 tombent en début de
| journée ouvrée. Cadence/fuseau affinables ultérieurement par tenant.
*/
Schedule::command('surestaries:rafraichir')->dailyAt('05:00');

/*
| Poll tracking : chaque heure, on réveille les conteneurs échus (le job filtre
| par prochain_poll_prevu ; l'heure ne fait que déclencher). L'économie d'appels
| vient du scheduler intelligent (docs/08), pas de la cadence de cette commande.
*/
Schedule::command('tracking:poller')->hourly();
