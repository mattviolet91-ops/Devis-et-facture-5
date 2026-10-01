<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AssuranceBientotFinie;
use App\Support\Alertes;
use App\Support\Reglages;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Prévient le gérant avant la fin de l'assurance décennale :
 * une fois au début de la période d'alerte, une fois 7 jours avant, une fois à l'échéance.
 */
class AlerteAssurance extends Command
{
    protected $signature = 'app:alerte-assurance';

    protected $description = 'Prévient le gérant quand l\'assurance décennale arrive à échéance';

    public function handle(Reglages $reglages): int
    {
        $fin = (string) reglage('assurance.date_fin');
        if ($fin === '') {
            return self::SUCCESS;
        }

        $dateFin = CarbonImmutable::parse($fin)->startOfDay();
        $jours = (int) now()->startOfDay()->diffInDays($dateFin, false);
        $alerte = (int) reglage('assurance.alerte_jours');

        $palier = match (true) {
            $jours < 0 => 'expiree',
            $jours <= 7 => '7',
            $jours <= $alerte => 'debut',
            default => null,
        };

        $cleDejaFait = "assurance.alerte_{$fin}_{$palier}";
        if ($palier === null || reglage($cleDejaFait)) {
            return self::SUCCESS;
        }

        Alertes::envoyer(User::gerantsActifs()->get(), new AssuranceBientotFinie($dateFin, $jours));

        $reglages->set($cleDejaFait, now()->toIso8601String());
        $this->info('Alerte envoyée au gérant.');

        return self::SUCCESS;
    }
}
