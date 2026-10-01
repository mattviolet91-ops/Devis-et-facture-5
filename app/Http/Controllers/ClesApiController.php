<?php

namespace App\Http\Controllers;

use App\Models\CleApi;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Réglages → Accès Claude : clés d'accès à la petite API (gérant).
 */
class ClesApiController extends Controller
{
    public function index(): View
    {
        return view('reglages.acces-claude', [
            'cles' => CleApi::with('user')->latest()->get(),
            'adresseApi' => url('/api/v1'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $nom = $request->validate(['nom' => ['required', 'string', 'max:100']], [], ['nom' => 'nom de la clé'])['nom'];
        [$cle, $enClair] = CleApi::creer($nom, $request->user());
        Journal::ecrire('api.cle', 'Clé d\'accès Claude créée : '.$cle->nom, $cle);

        // La clé n'est montrée qu'une seule fois (session flash), jamais enregistrée en clair.
        return redirect()->route('reglages.claude')->with('cle_creee', $enClair)->with('statut', 'Clé créée. Copiez-la maintenant : elle ne sera plus affichée.');
    }

    public function revoquer(CleApi $cle): RedirectResponse
    {
        $cle->forceFill(['revoquee_at' => now()])->save();
        Journal::ecrire('api.cle', 'Clé d\'accès Claude révoquée : '.$cle->nom, $cle);

        return back()->with('statut', 'Clé révoquée : elle ne fonctionne plus.');
    }
}
