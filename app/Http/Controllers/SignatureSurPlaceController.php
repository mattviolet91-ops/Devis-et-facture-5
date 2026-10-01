<?php

namespace App\Http\Controllers;

use App\Models\Devis;
use App\Models\LienClient;
use App\Services\SignatureDevis;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Signature sur place, sur le téléphone de l'artisan, et lien client à partager.
 */
class SignatureSurPlaceController extends Controller
{
    public function create(Devis $devis): View|RedirectResponse
    {
        if (! $devis->peutEtreSigne()) {
            return redirect()->route('devis.show', $devis)->withErrors(['devis' => 'Seul un devis envoyé et encore valable peut être signé.']);
        }

        return view('devis.signer', ['devis' => $devis->load(['client', 'lignes']), 'detail' => $devis->detailTotaux()]);
    }

    public function store(Request $request, Devis $devis, SignatureDevis $signatures): RedirectResponse
    {
        $donnees = EspaceClientController::validerSignature($request);
        $signatures->signer($devis, $donnees['nom'], $donnees['signature'], $request->ip(), $request->userAgent(), true, $request->boolean('execution_immediate'));

        return redirect()->route('devis.show', $devis)->with('statut', 'Devis signé sur place. Le PDF signé est enregistré.');
    }

    public function lien(Devis $devis): RedirectResponse
    {
        if ($devis->statut === Devis::BROUILLON) {
            return back()->withErrors(['devis' => 'Marquez d\'abord le devis comme envoyé : il recevra son numéro.']);
        }

        LienClient::pour($devis);

        return redirect()->to(route('devis.show', $devis).'#lien-client');
    }
}
