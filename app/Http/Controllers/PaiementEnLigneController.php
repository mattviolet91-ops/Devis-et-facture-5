<?php

namespace App\Http\Controllers;

use App\Models\Facture;
use App\Models\LienClient;
use App\Services\MyPos;
use App\Support\Configuration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Paiement par carte avec myPOS Checkout.
 */
class PaiementEnLigneController extends Controller
{
    public function __construct(private MyPos $mypos) {}

    /**
     * Client : « Payer par carte » (montant exact du reste à payer).
     */
    public function payer(string $jeton): Response
    {
        $facture = $this->factureDuLien($jeton);
        abort_unless($this->mypos->visiblePourLesClients() && $facture->resteAPayer() > 0, 404);

        return $this->redirection($this->mypos->preparer($facture, false));
    }

    /**
     * Gérant : lien d'essai en mode test (aucun argent réel, rien n'est enregistré).
     */
    public function essai(Facture $facture): Response|RedirectResponse
    {
        if ($this->mypos->mode() !== 'test') {
            return back()->withErrors(['paiement' => 'Le lien d\'essai marche seulement en mode test (Réglages → Paiement en ligne).']);
        }
        if ($facture->resteAPayer() <= 0) {
            return back()->withErrors(['paiement' => 'Cette facture est déjà réglée.']);
        }

        return $this->redirection($this->mypos->preparer($facture, true));
    }

    public function merci(string $jeton): View
    {
        $facture = $this->factureDuLien($jeton);

        return view('client.paiement-merci', ['facture' => $facture, 'lien' => LienClient::trouver($jeton)]);
    }

    public function annule(Request $request, string $jeton): RedirectResponse
    {
        $facture = $this->factureDuLien($jeton);
        $this->mypos->annuler($facture, (string) ($request->input('commande') ?? $request->input('OrderID')));

        return redirect()->route('client.document', $jeton)->with('statut', 'Paiement annulé. Vous pouvez réessayer quand vous voulez.');
    }

    /**
     * Notification de serveur à serveur (IPCPurchaseNotify). Réponse « OK » si acceptée.
     */
    public function notification(Request $request): Response
    {
        return $this->mypos->notification($request->post())
            ? response('OK', 200, ['Content-Type' => 'text/plain'])
            : response('Refusé', 400, ['Content-Type' => 'text/plain']);
    }

    /**
     * Page qui envoie le formulaire signé vers la page de paiement myPOS.
     *
     * @param  array{url: string, champs: array<string, string>}  $formulaire
     */
    private function redirection(array $formulaire): Response
    {
        return response()->view('client.vers-mypos', $formulaire)
            // La page envoie un formulaire à myPOS : on l'autorise pour cette page seulement.
            ->header('X-Form-Action-Extra', 'https://www.mypos.com');
    }

    private function factureDuLien(string $jeton): Facture
    {
        abort_unless(Configuration::accesClientsActif(), 503);
        $lien = LienClient::trouver($jeton);
        abort_unless($lien && $lien->document instanceof Facture, 404);

        return $lien->document;
    }
}
