<?php

namespace App\Console\Commands;

use App\Models\RendezVous;
use App\Models\User;
use App\Services\EnvoiEmail;
use App\Services\NotificationsTelephone;
use Illuminate\Console\Command;

/**
 * Rappels du planning (toutes les 5 minutes) :
 * - notification 1 heure avant un rendez-vous ;
 * - notification la veille à 19 h (rendez-vous et chantiers du lendemain) ;
 * - email au client 1 ou 2 jours avant, à 9 h, si l'option est cochée.
 */
class RappelsPlanning extends Command
{
    protected $signature = 'app:rappels-planning';

    protected $description = 'Envoie les rappels des rendez-vous et chantiers';

    public function handle(NotificationsTelephone $telephone, EnvoiEmail $emails): int
    {
        $maintenant = now();
        $envois = 0;

        RendezVous::with(['client', 'chantier', 'user', 'createur'])
            ->where('fait', false)
            ->whereBetween('debut', [$maintenant, $maintenant->copy()->addDays(3)])
            ->get()
            ->each(function (RendezVous $rdv) use ($maintenant, $telephone, $emails, &$envois) {
                $personne = $rdv->user ?? $rdv->createur ?? User::gerantsActifs()->first();
                $adresse = $rdv->adresse();

                if (! $rdv->journee_entiere && ! $rdv->rappelEnvoye('heure') && $maintenant->gte($rdv->debut->copy()->subHour())) {
                    $rdv->noterRappel('heure');
                    if ($personne) {
                        $envois += $telephone->envoyer($personne, 'Dans 1 heure : '.$rdv->titre, trim($rdv->horaire().' · '.$adresse, ' ·'), route('planning.show', $rdv));
                    }
                }

                $veille = $rdv->debut->copy()->subDay()->setTime(19, 0);
                if (! $rdv->rappelEnvoye('veille') && $maintenant->gte($veille) && $maintenant->lt($rdv->debut)) {
                    $rdv->noterRappel('veille');
                    if ($personne && $maintenant->lt($veille->copy()->addHours(5))) {
                        $envois += $telephone->envoyer($personne, 'Demain : '.$rdv->titre, trim($rdv->horaire().' · '.$adresse, ' ·'), route('planning.show', $rdv));
                    }
                }

                $jours = (int) $rdv->rappel_client_jours;
                if ($jours > 0 && ! $rdv->rappelEnvoye('client') && $rdv->client?->email) {
                    $moment = $rdv->debut->copy()->startOfDay()->subDays($jours)->setTime(9, 0);
                    if ($maintenant->gte($moment)) {
                        $rdv->noterRappel('client');
                        $variables = $emails->variablesRendezVous($rdv);
                        $emails->envoyer(
                            $rdv,
                            $rdv->client->email,
                            strtr((string) reglage('emails.modeles.rendez_vous.sujet'), $variables),
                            strtr((string) reglage('emails.modeles.rendez_vous.corps'), $variables),
                            'rendez_vous', false, null, true,
                        );
                        $envois++;
                    }
                }
            });

        $this->info("{$envois} rappel(s) envoyé(s).");

        return self::SUCCESS;
    }
}
