<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Services\Sauvegardes;
use App\Support\Alertes;
use Illuminate\Console\Command;

/**
 * Sauvegarde chaque nuit la base ; les fichiers le 1er du mois (ou avec --fichiers).
 */
class Sauvegarder extends Command
{
    protected $signature = 'app:sauvegarder {--fichiers : Sauvegarder aussi les fichiers (photos, PDF…)}';

    protected $description = 'Sauvegarde la base de données (et les fichiers le 1er du mois)';

    public function handle(Sauvegardes $sauvegardes): int
    {
        try {
            $this->info('Base : '.$sauvegardes->sauvegarderBase());
            if ($this->option('fichiers') || now()->day === 1 || ! $sauvegardes->derniere('fichiers')) {
                $this->info('Fichiers : '.$sauvegardes->sauvegarderFichiers());
            }
        } catch (\Throwable $e) {
            $this->error('Sauvegarde impossible : '.$e->getMessage());
            Alertes::envoyer(User::gerantsActifs()->get(), new AlerteDocument(
                'Sauvegarde impossible',
                'La sauvegarde de cette nuit n\'a pas pu se faire. Vérifiez l\'espace disque de l\'hébergement.',
                route('reglages.sauvegardes'),
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
