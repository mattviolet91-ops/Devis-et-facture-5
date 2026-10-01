<?php

namespace App\Http\Controllers;

use App\Services\ImportClients;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ImportClientsController extends Controller
{
    public function create(): View
    {
        return view('clients.import', ['champs' => array_keys(ImportClients::COLONNES)]);
    }

    public function analyser(Request $request, ImportClients $import): RedirectResponse
    {
        $request->validate([
            'fichier' => ['required', 'file', 'max:2048', 'mimes:csv,txt'],
        ], [
            'fichier.required' => 'Choisissez un fichier CSV.',
            'fichier.mimes' => 'Le fichier doit être au format CSV (dans Excel : Enregistrer sous → CSV).',
            'fichier.max' => 'Le fichier ne doit pas dépasser 2 Mo.',
        ]);

        $analyse = $import->analyser($request->file('fichier')->getRealPath());

        if (! in_array('nom', $analyse['colonnes'], true) && ! in_array('raison_sociale', $analyse['colonnes'], true)) {
            return back()->withErrors(['fichier' => 'Aucune colonne « Nom » ou « Société » trouvée. Vérifiez la première ligne du fichier (les titres des colonnes).']);
        }

        $jeton = Str::random(32);
        Cache::put($this->cle($request, $jeton), $analyse, now()->addHour());

        return redirect()->route('clients.import.apercu', $jeton);
    }

    public function apercu(Request $request, string $jeton): View|RedirectResponse
    {
        $analyse = Cache::get($this->cle($request, $jeton));

        if (! $analyse) {
            return redirect()->route('clients.import')->withErrors(['fichier' => 'L\'aperçu a expiré. Renvoyez le fichier.']);
        }

        $lignes = collect($analyse['lignes']);

        return view('clients.import-apercu', [
            'jeton' => $jeton,
            'colonnes' => $analyse['colonnes'],
            'lignes' => $lignes,
            'compte' => $lignes->countBy('statut')->all(),
        ]);
    }

    public function importer(Request $request, string $jeton, ImportClients $import): RedirectResponse
    {
        $cle = $this->cle($request, $jeton);
        $analyse = Cache::pull($cle);

        if (! $analyse) {
            return redirect()->route('clients.import')->withErrors(['fichier' => 'L\'aperçu a expiré. Renvoyez le fichier.']);
        }

        $resultat = $import->importer($analyse['lignes'], $request->boolean('avec_doublons'), $request->user()->id);
        Journal::ecrire('client.import', "Import CSV : {$resultat['crees']} client(s) créé(s), {$resultat['ignores']} ligne(s) ignorée(s)");

        return redirect()->route('clients.index')
            ->with('statut', "{$resultat['crees']} client(s) importé(s). {$resultat['ignores']} ligne(s) ignorée(s).");
    }

    private function cle(Request $request, string $jeton): string
    {
        return 'import-clients.'.$request->user()->id.'.'.$jeton;
    }
}
