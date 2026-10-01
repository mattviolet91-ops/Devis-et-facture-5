<?php

namespace App\Console\Commands;

use App\Models\Facture;
use App\Services\EnvoiEmail;
use Illuminate\Console\Command;

/**
 * Relances automatiques (option par facture) : par email, le lendemain de l'échéance
 * puis tous les 7 jours, 3 relances au maximum.
 */
class RelancerFactures extends Command
{
    protected $signature = 'app:relancer-factures';

    protected $description = 'Envoie les relances automatiques des factures impayées';

    public const MAXIMUM = 3;

    public function handle(EnvoiEmail $emails): int
    {
        $envoyees = 0;

        Facture::with('client')
            ->where('statut', Facture::EMISE)
            ->where('relances_auto', true)
            ->where('relances', '<', self::MAXIMUM)
            ->whereDate('date_echeance', '<', now()->toDateString())
            ->get()
            ->each(function (Facture $facture) use ($emails, &$envoyees) {
                if ($facture->resteAPayer() <= 0 || ! $facture->client?->email) {
                    return;
                }
                if ($facture->derniere_relance_at && $facture->derniere_relance_at->gt(now()->subDays(7))) {
                    return;
                }

                $texte = $emails->rediger($facture, 'relance');
                $emails->envoyer($facture, $facture->client->email, $texte['sujet'], $texte['corps'], 'relance', true, null, true);
                $facture->forceFill(['relances' => $facture->relances + 1, 'derniere_relance_at' => now()])->save();
                $envoyees++;
            });

        $this->info("{$envoyees} relance(s) envoyée(s).");

        return self::SUCCESS;
    }
}
