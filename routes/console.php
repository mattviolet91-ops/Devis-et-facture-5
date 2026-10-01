<?php

use Illuminate\Support\Facades\Schedule;

// Hébergement mutualisé : le cron lance « php artisan schedule:run » chaque minute.
Schedule::command('app:purger-corbeille')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('app:alerte-assurance')->dailyAt('08:05')->withoutOverlapping();
Schedule::command('app:expirer-devis')->dailyAt('00:20')->withoutOverlapping();
Schedule::command('app:relancer-factures')->dailyAt('09:10')->withoutOverlapping();
Schedule::command('app:rappels-planning')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('app:meteo')->hourlyAt(17)->withoutOverlapping();
Schedule::command('app:relancer-devis')->dailyAt('09:20')->withoutOverlapping();
Schedule::command('app:lire-emails')->everyFiveMinutes()->withoutOverlapping();
