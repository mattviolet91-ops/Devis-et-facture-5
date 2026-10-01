<?php

namespace App\Console\Commands;

use App\Models\Devis;
use App\Services\EnvoiEmail;
use App\Support\Journal;
use Illuminate\Console\Command;

/**
 * Relances automatiques des devis envoyés sans réponse : à 7 jours puis à 15 jours.
 */
class RelancerDevis extends Command
{
    protected $signature = 'app:relancer-devis';

    protected $description = 'Relance par email les devis envoyés sans réponse (7 et 15 jours)';

    /** Jours après l'envoi pour chaque relance. */
    public const JOURS = [7, 15];

    public function handle(EnvoiEmail $emails): int
    {
        if (! reglage('suivi.relances_devis')) {
            $this->info('Relances de devis désactivées.');

            return self::SUCCESS;
        }

        $envoyees = 0;
        Devis::with('client')
            ->where('statut', Devis::ENVOYE)
            ->where('relances_auto', true)
            ->where('relances', '<', count(self::JOURS))
            ->whereNotNull('envoye_at')
            ->get()
            ->each(function (Devis $devis) use ($emails, &$envoyees) {
                if (! $devis->client?->email || ! $devis->peutEtreSigne()) {
                    return;
                }
                $echeance = $devis->envoye_at->copy()->addDays(self::JOURS[$devis->relances]);
                if (now()->lt($echeance)) {
                    return;
                }

                $texte = $emails->rediger($devis, 'relance_devis');
                $emails->envoyer($devis, $devis->client->email, $texte['sujet'], $texte['corps'], 'relance_devis', false, null, true);
                $devis->forceFill(['relances' => $devis->relances + 1, 'derniere_relance_at' => now()])->save();
                Journal::ecrire('devis.relance', 'Relance automatique n° '.$devis->relances.' du devis '.$devis->reference(), $devis);
                $envoyees++;
            });

        $this->info("{$envoyees} relance(s) de devis envoyée(s).");

        return self::SUCCESS;
    }
}
