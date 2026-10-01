<?php

namespace App\Console\Commands;

use App\Services\BoiteEmails;
use App\Services\LectureDemandes;
use Illuminate\Console\Command;

/**
 * Lit les derniers emails (lecture seule) et repère les demandes du site WordPress.
 */
class LireEmails extends Command
{
    protected $signature = 'app:lire-emails';

    protected $description = 'Lit la boîte de réception et repère les demandes de devis du site';

    public function handle(BoiteEmails $boite, LectureDemandes $lecture): int
    {
        if (! $boite->estConfiguree()) {
            $this->info('Lecture des emails désactivée ou Gmail pas encore réglé.');

            return self::SUCCESS;
        }

        try {
            $nouvelles = $lecture->importer($boite->derniers());
        } catch (\Throwable $e) {
            // Le message d'erreur peut contenir l'adresse, jamais le mot de passe.
            $this->warn('Boîte de réception injoignable : '.class_basename($e));

            return self::FAILURE;
        }

        $this->info("{$nouvelles} nouvelle(s) demande(s).");

        return self::SUCCESS;
    }
}
