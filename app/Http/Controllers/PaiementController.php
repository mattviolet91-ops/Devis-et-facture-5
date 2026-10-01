<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\Paiement;
use App\Services\Encaissements;
use App\Services\GestionFactures;
use App\Support\Montant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Encaissements saisis par le gérant (virement, carte, chèque, espèces).
 */
class PaiementController extends Controller
{
    public function __construct(private Encaissements $encaissements) {}

    public function store(Request $request, Facture $facture): RedirectResponse
    {
        $donnees = $request->validate([
            'montant' => ['required', 'string', 'max:20'],
            'mode' => ['required', Rule::in(['virement', 'carte', 'cheque', 'especes'])],
            'date_paiement' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['date_paiement.before_or_equal' => 'La date ne peut pas être dans le futur.'], ['date_paiement' => 'date']);

        $montant = Montant::lire($donnees['montant']);
        if ($montant === null) {
            throw ValidationException::withMessages(['montant' => 'Montant illisible (exemple : 250,00).']);
        }

        $this->encaissements->enregistrer($facture, $montant, $donnees['mode'], $donnees['date_paiement'], $donnees['reference'] ?? null, $donnees['notes'] ?? null, $request->user()->id);

        $retour = redirect()->route('factures.show', $facture)->with('statut', Montant::formater($montant).' encaissé. Reste à payer : '.Montant::formater($facture->fresh()->resteAPayer()).'.');
        if ($rappel = GestionFactures::rappelSeptJours($facture->devis)) {
            $retour->with('rappel', $rappel);
        }

        return $retour;
    }

    public function annuler(Request $request, Paiement $paiement): RedirectResponse
    {
        $motif = $request->validate(['motif' => ['required', 'string', 'max:300']], ['motif.required' => 'Indiquez pourquoi vous annulez cet encaissement.'])['motif'];

        $this->encaissements->annuler($paiement, $motif, $request->user()->id);

        return redirect()->route('factures.show', $paiement->facture_id)->with('statut', 'Encaissement annulé (une écriture d\'annulation a été ajoutée).');
    }
}
