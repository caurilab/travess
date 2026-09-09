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
