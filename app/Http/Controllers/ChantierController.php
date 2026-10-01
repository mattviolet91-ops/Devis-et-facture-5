<?php

namespace App\Http\Controllers;

use App\Models\Chantier;
use App\Models\Client;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChantierController extends Controller
{
    public function create(Client $client): View
    {
        return view('chantiers.formulaire', [
            'client' => $client,
            'chantier' => new Chantier([
                'adresse' => $client->chantiers()->exists() ? null : $client->adresse,
                'code_postal' => $client->chantiers()->exists() ? null : $client->code_postal,
                'ville' => $client->chantiers()->exists() ? null : $client->ville,
            ]),
        ]);
    }

    public function store(Request $request, Client $client): RedirectResponse
    {
        $chantier = $client->chantiers()->create($this->valider($request));
        Journal::ecrire('chantier.creation', 'Adresse de chantier ajoutée pour '.$client->nomComplet(), $chantier);

        return redirect()->route('clients.show', $client)->with('statut', 'Adresse de chantier enregistrée.');
    }

    public function edit(Chantier $chantier): View
    {
        return view('chantiers.formulaire', ['client' => $chantier->client, 'chantier' => $chantier]);
    }

    public function update(Request $request, Chantier $chantier): RedirectResponse
    {
        $chantier->update($this->valider($request));

        return redirect()->route('clients.show', $chantier->client)->with('statut', 'Adresse de chantier modifiée.');
    }

    public function destroy(Chantier $chantier): RedirectResponse
    {
        $chantier->delete();
        Journal::ecrire('chantier.suppression', 'Adresse de chantier mise à la corbeille : '.$chantier->titre(), $chantier);

        return redirect()->route('clients.show', $chantier->client)->with('statut', 'Adresse de chantier mise à la corbeille.');
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request): array
    {
        $request->merge(['surface' => $request->filled('surface') ? str_replace([',', ' '], ['.', ''], (string) $request->input('surface')) : null]);

        return $request->validate([
            'libelle' => ['nullable', 'string', 'max:120'],
            'adresse' => ['required', 'string', 'max:200'],
            'code_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'ville' => ['required', 'string', 'max:100'],
            'type_toiture' => ['nullable', Rule::in(Chantier::TYPES_TOITURE)],
            'surface' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'pente' => ['nullable', 'integer', 'min:0', 'max:90'],
            'acces' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'code_postal.regex' => 'Le code postal fait 5 chiffres.',
            'pente.max' => 'La pente se donne en degrés (entre 0 et 90°).',
        ], [
            'libelle' => 'nom du chantier', 'code_postal' => 'code postal', 'type_toiture' => 'type de toiture', 'acces' => 'accès',
        ]);
    }
}
