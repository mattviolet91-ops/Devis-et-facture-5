<?php

namespace App\Support;

use App\Models\Facture;
use App\Models\Paiement;
use Illuminate\Support\Carbon;

/**
 * Chiffre d'affaires et encaissements (gérant seulement).
 */
class Chiffres
{
    /**
     * Facturé HT sur la période : factures émises moins les avoirs.
     *
     * @param  list<int>|null  $clients
     */
    public static function factureHt(Carbon $debut, Carbon $fin, ?array $clients = null): int
    {
        $requete = fn () => Facture::whereIn('statut', [Facture::EMISE, Facture::PAYEE])
            ->whereDate('date_facture', '>=', $debut->toDateString())->whereDate('date_facture', '<=', $fin->toDateString())
            ->when($clients !== null, fn ($q) => $q->whereIn('client_id', $clients));

        return (int) $requete()->where('type', '!=', Facture::AVOIR)->sum('total_ht')
            - (int) $requete()->where('type', Facture::AVOIR)->sum('total_ht');
    }

    public static function encaisse(Carbon $debut, Carbon $fin): int
    {
        return (int) Paiement::whereDate('date_paiement', '>=', $debut->toDateString())->whereDate('date_paiement', '<=', $fin->toDateString())->sum('montant');
    }
}
