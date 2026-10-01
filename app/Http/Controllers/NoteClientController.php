<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\NoteClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NoteClientController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $donnees = $request->validate(['texte' => ['required', 'string', 'max:5000']], [], ['texte' => 'note']);

        $client->notes()->create($donnees + ['user_id' => $request->user()->id]);

        return redirect()->to(route('clients.show', $client).'#notes')->with('statut', 'Note ajoutée.');
    }

    public function destroy(Request $request, NoteClient $note): RedirectResponse
    {
        // Chacun supprime ses propres notes ; le gérant peut tout supprimer.
        abort_unless($request->user()->estGerant() || $note->user_id === $request->user()->id, 403);

        $client = $note->client_id;
        $note->delete();

        return redirect()->to(route('clients.show', $client).'#notes')->with('statut', 'Note supprimée.');
    }
}
