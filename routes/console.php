<?php

use Illuminate\Support\Facades\Schedule;

// Hébergement mutualisé : le cron lance « php artisan schedule:run » chaque minute.
Schedule::command('app:purger-corbeille')->dailyAt('03:15')->withoutOverlapping();
