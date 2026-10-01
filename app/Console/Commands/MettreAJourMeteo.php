<?php

namespace App\Console\Commands;

use App\Models\MeteoPrevision;
use App\Models\RendezVous;
use App\Services\Meteo;
use Illuminate\Console\Command;

/**
 * Tâche horaire : situe les adresses des rendez-vous à venir et met la météo en cache.
 */
class MettreAJourMeteo extends Command
{
    protected $signature = 'app:meteo';

    protected $description = 'Met à jour la météo des chantiers et rendez-vous des prochains jours';

    public function handle(Meteo $meteo): int
    {
        $points = [];
        RendezVous::with(['client', 'chantier'])
            ->entre(now()->startOfDay(), now()->addDays(Meteo::JOURS)->endOfDay())
            ->get()
            ->each(function (RendezVous $rdv) use ($meteo, &$points) {
                $adresse = $rdv->adresse();
                if (! $adresse) {
                    return;
                }
                try {
                    $lieu = $meteo->situer($adresse, true);
                } catch (\Throwable $e) {
                    $this->warn('Adresse non située : '.$e->getMessage());

                    return;
                }
                if ($lieu) {
                    $points[Meteo::point((float) $lieu->latitude, (float) $lieu->longitude)] = true;
                }
            });

        $jours = 0;
        foreach (array_keys($points) as $point) {
            try {
                $jours += $meteo->telecharger($point);
            } catch (\Throwable $e) {
                $this->warn('Météo indisponible pour '.$point.' : '.$e->getMessage());
            }
        }

        // Ménage : prévisions passées.
        MeteoPrevision::where('jour', '<', now()->subDays(2)->toDateString())->delete();

        $this->info(count($points).' lieu(x), '.$jours.' jour(s) de prévision.');

        return self::SUCCESS;
    }
}
