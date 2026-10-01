<?php

namespace App\Http\Controllers;

use App\Services\DevisExpress;
use App\Services\GestionDevis;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Devis express : phrase écrite ou dictée → aperçu obligatoire → brouillon.
 * Rien n'est envoyé au client.
 */
class DevisExpressController extends Controller
{
    public function create(): View
    {
        return view('devis.express', ['phrase' => old('phrase', ''), 'resultat' => null]);
    }

    public function apercu(Request $request, DevisExpress $express): View
    {
        $phrase = $request->validate(['phrase' => ['required', 'string', 'max:3000']], ['phrase.required' => 'Écrivez ou dictez votre devis.'])['phrase'];

        return view('devis.express', ['phrase' => $phrase, 'resultat' => $express->analyser($phrase)]);
    }

    public function store(Request $request, DevisExpress $express, GestionDevis $gestion): RedirectResponse
    {
        $phrase = $request->validate(['phrase' => ['required', 'string', 'max:3000']])['phrase'];

        // On ré-analyse : ce qui est créé correspond exactement à l'aperçu affiché.
        $resultat = $express->analyser($phrase);

        if ($resultat['erreurs'] || ! $resultat['client']) {
            return redirect()->route('devis.express')->withInput()
                ->withErrors(['phrase' => 'Corrigez la phrase avant de créer le brouillon.']);
        }

        $devis = $gestion->creer($resultat['client'], null, null, $request->user()->id);
        $gestion->remplacerLignes($devis, $resultat['lignes']);
        Journal::ecrire('devis.express', 'Devis express créé pour '.$resultat['client']->nomComplet(), $devis);

        return redirect()->route('devis.edit', $devis)->with('statut', 'Brouillon créé. Vérifiez-le : rien n\'a été envoyé au client.');
    }
}
