<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $recherche = trim((string) $request->query('q'));

        $clients = Client::query()
            ->when($recherche !== '', fn ($q) => $q->recherche($recherche))
            ->when(in_array($request->query('type'), [Client::PARTICULIER, Client::PROFESSIONNEL], true), fn ($q) => $q->where('type', $request->query('type')))
            ->orderByRaw('COALESCE(raison_sociale, nom)')
            ->orderBy('prenom')
            ->paginate(30)
            ->withQueryString();

        return view('clients.index', [
            'clients' => $clients,
            'recherche' => $recherche,
            'total' => Client::count(),
        ]);
    }

    public function create(): View
    {
        return view('clients.formulaire', ['client' => new Client(['type' => Client::PARTICULIER])]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        if ($retour = $this->alerteDoublon($request)) {
            return $retour;
        }

        $client = Client::create($request->donnees() + ['created_by' => $request->user()->id]);
        Journal::ecrire('client.creation', 'Client créé : '.$client->nomComplet(), $client);

        return redirect()->route('clients.show', $client)->with('statut', 'Client enregistré.');
    }

    public function show(Client $client): View
    {
        $client->load(['chantiers', 'notes.user', 'piecesJointes']);

        return view('clients.show', ['client' => $client]);
    }

    public function edit(Client $client): View
    {
        return view('clients.formulaire', ['client' => $client]);
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        if ($retour = $this->alerteDoublon($request, $client)) {
            return $retour;
        }

        $client->update($request->donnees());
        Journal::ecrire('client.modification', 'Client modifié : '.$client->nomComplet(), $client);

        return redirect()->route('clients.show', $client)->with('statut', 'Modifications enregistrées.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();
        Journal::ecrire('client.suppression', 'Client mis à la corbeille : '.$client->nomComplet(), $client);

        return redirect()->route('clients.index')
            ->with('statut', 'Client mis à la corbeille. Le gérant peut le remettre pendant 30 jours.');
    }

    /**
     * Même téléphone ou même email qu'un client existant : on prévient avant d'enregistrer.
     */
    private function alerteDoublon(ClientRequest $request, ?Client $client = null): ?RedirectResponse
    {
        if ($request->boolean('confirmer_doublon')) {
            return null;
        }

        $doublons = Client::doublons($request->input('telephone'), $request->input('email'), $client?->id);

        if ($doublons->isEmpty()) {
            return null;
        }

        return back()->withInput()->with('doublons', $doublons->map(fn (Client $c) => [
            'id' => $c->id,
            'nom' => $c->nomComplet(),
            'ville' => $c->ville,
        ])->all());
    }
}
