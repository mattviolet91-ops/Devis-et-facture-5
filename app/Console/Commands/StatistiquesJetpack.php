<?php

namespace App\Console\Commands;

use App\Models\VisiteSite;
use App\Services\Jetpack;
use Illuminate\Console\Command;

class StatistiquesJetpack extends Command
{
    protected $signature = 'app:stats-site';

    protected $description = 'Met à jour les statistiques Jetpack et efface les visites de plus de 13 mois';

    public function handle(Jetpack $jetpack): int
    {
        VisiteSite::where('jour', '<', now()->subMonths(VisiteSite::CONSERVATION_MOIS)->toDateString())->delete();

        if (! $jetpack->estConnecte()) {
            $this->info('Jetpack non connecté.');

            return self::SUCCESS;
        }

        try {
            $ok = $jetpack->mettreAJour();
        } catch (\Throwable $e) {
            $this->warn('Jetpack injoignable : '.class_basename($e));

            return self::FAILURE;
        }

        $this->info($ok ? 'Statistiques Jetpack mises à jour.' : 'Jetpack a refusé la demande (reconnectez le compte).');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
