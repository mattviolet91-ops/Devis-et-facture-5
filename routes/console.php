<?php

use Illuminate\Support\Facades\Schedule;

// Hébergement mutualisé : le cron lance « php artisan schedule:run » chaque minute.
Schedule::command('app:purger-corbeille')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('app:alerte-assurance')->dailyAt('08:05')->withoutOverlapping();
Schedule::command('app:expirer-devis')->dailyAt('00:20')->withoutOverlapping();
