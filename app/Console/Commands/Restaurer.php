<?php

namespace App\Console\Commands;

use App\Services\Sauvegardes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Remet une sauvegarde de la base. Remplace TOUTES les données actuelles :
 * une sauvegarde de l'état actuel est faite juste avant.
 */
class Restaurer extends Command
{
    protected $signature = 'app:restaurer {fichier : Nom de la sauvegarde (base-….json.gz) ou chemin complet} {--force : Ne pas demander de confirmation}';

    protected $description = 'Remet une sauvegarde de la base de données (remplace les données actuelles)';

    public function handle(Sauvegardes $sauvegardes): int
    {
        $fichier = (string) $this->argument('fichier');
        $chemin = Sauvegardes::nomValide($fichier) ? Storage::disk('local')->path(Sauvegardes::DOSSIER.'/'.$fichier) : $fichier;
        if (! is_file($chemin) || ! str_ends_with($chemin, '.json.gz')) {
            $this->error('Sauvegarde introuvable : '.$fichier);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Toutes les données actuelles seront remplacées par celles de la sauvegarde. Continuer ?', false)) {
            $this->info('Rien n\'a été changé.');

            return self::FAILURE;
        }

        $securite = $sauvegardes->sauvegarderBase();
        $this->info('Sauvegarde de l\'état actuel avant restauration : '.$securite);

        $total = $sauvegardes->restaurerBase($chemin);
        $this->info("Sauvegarde remise : {$total} enregistrement(s).");

        return self::SUCCESS;
    }
}
