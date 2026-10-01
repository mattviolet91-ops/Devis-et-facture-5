<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\AlerteDocument;
use App\Support\Alertes;
use App\Support\Journal;
use Illuminate\Console\Command;

/**
 * Prévient le gérant après une mise à jour automatique (outils/deploy.sh).
 */
class MiseAJour extends Command
{
    protected $signature = 'app:mise-a-jour {--echec : La mise à jour a échoué et a été annulée}';

    protected $description = 'Prévient le gérant qu\'une mise à jour a été faite (ou annulée)';

    public function handle(): int
    {
        $echec = (bool) $this->option('echec');
        Journal::ecrire($echec ? 'mise_a_jour.echec' : 'mise_a_jour', $echec ? 'Mise à jour annulée (retour à la version d\'avant)' : 'Application mise à jour');
        Alertes::envoyer(User::gerantsActifs()->get(), new AlerteDocument(
            $echec ? 'Mise à jour annulée' : 'Application mise à jour',
            $echec
                ? 'Une mise à jour automatique n\'a pas pu se faire : l\'application est restée sur la version d\'avant. Rien n\'est perdu.'
                : 'L\'application vient d\'être mise à jour. Une sauvegarde a été faite juste avant.',
            route('accueil'),
        ));

        return self::SUCCESS;
    }
}
