<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\LienClient;
use App\Models\Rapport;
use App\Models\RendezVous;
use App\Services\PdfRapport;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RapportController extends Controller
{
    public function create(Request $request): View
    {
        $rdv = $request->filled('rdv') ? RendezVous::findOrFail($request->integer('rdv')) : null;
        $client = $rdv?->client ?? Client::findOrFail($request->integer('client'));
        abort_unless($client, 404);

        $rapport = new Rapport([
            'client_id' => $client->id,
            'chantier_id' => $rdv?->chantier_id,
            'rendez_vous_id' => $rdv?->id,
            'date_intervention' => $rdv ? $rdv->debut : today(),
            'titre' => $rdv?->titre ?? '',
        ]);
        $rapport->photos = $client->photos()
            ->when($rdv, fn ($q) => $q->where(fn ($q) => $q->where('rendez_vous_id', $rdv->id)->orWhere('chantier_id', $rdv->chantier_id)))
            ->pluck('id')->all();

        return view('rapports.formulaire', ['rapport' => $rapport, 'client' => $client]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rapport = Rapport::create($this->valider($request) + ['created_by' => $request->user()->id]);
        Journal::ecrire('rapport.creation', 'Rapport d\'intervention : '.$rapport->titre, $rapport);

        return redirect()->route('rapports.show', $rapport)->with('statut', 'Rapport enregistré.');
    }

    public function show(Rapport $rapport): View
    {
        return view('rapports.show', ['rapport' => $rapport->load(['client', 'chantier']), 'photos' => $rapport->lesPhotos()]);
    }

    public function edit(Rapport $rapport): View
    {
        return view('rapports.formulaire', ['rapport' => $rapport, 'client' => $rapport->client]);
    }

    public function update(Request $request, Rapport $rapport): RedirectResponse
    {
        $rapport->update($this->valider($request, $rapport->client_id));

        return redirect()->route('rapports.show', $rapport)->with('statut', 'Rapport modifié.');
    }

    public function destroy(Rapport $rapport): RedirectResponse
    {
        $rapport->delete();
        Journal::ecrire('rapport.suppression', 'Rapport mis à la corbeille : '.$rapport->titre, $rapport);

        return redirect()->route('clients.show', $rapport->client_id)->with('statut', 'Rapport mis à la corbeille.');
    }

    public function pdf(Rapport $rapport, PdfRapport $pdf): Response
    {
        return response($pdf->generer($rapport), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="rapport-'.$rapport->date_intervention->format('Y-m-d').'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function lien(Rapport $rapport): RedirectResponse
    {
        LienClient::pour($rapport);

        return back()->with('statut', 'Lien client créé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request, ?int $clientId = null): array
    {
        $clientId ??= $request->integer('client_id');
        $donnees = $request->validate([
            'client_id' => [$clientId ? 'nullable' : 'required', 'integer', 'exists:clients,id'],
            'chantier_id' => ['nullable', 'integer', Rule::exists('chantiers', 'id')->where('client_id', $clientId)],
            'rendez_vous_id' => ['nullable', 'integer', 'exists:rendez_vous,id'],
            'date_intervention' => ['required', 'date_format:Y-m-d'],
            'titre' => ['required', 'string', 'max:200'],
            'travaux' => ['nullable', 'string', 'max:10000'],
            'constats' => ['nullable', 'string', 'max:10000'],
            'conseils' => ['nullable', 'string', 'max:10000'],
            'photos' => ['nullable', 'array', 'max:60'],
            'photos.*' => ['integer', Rule::exists('photos', 'id')->where('client_id', $clientId)],
        ], [], ['titre' => 'objet', 'date_intervention' => 'date d\'intervention', 'constats' => 'constats']);

        return [
            'client_id' => $clientId,
            'chantier_id' => $donnees['chantier_id'] ?? null,
            'rendez_vous_id' => $donnees['rendez_vous_id'] ?? null,
            'date_intervention' => $donnees['date_intervention'],
            'titre' => Str::limit($donnees['titre'], 200, ''),
            'travaux' => $donnees['travaux'] ?? null,
            'constats' => $donnees['constats'] ?? null,
            'conseils' => $donnees['conseils'] ?? null,
            'photos' => array_values(array_map('intval', $donnees['photos'] ?? [])),
        ];
    }
}
