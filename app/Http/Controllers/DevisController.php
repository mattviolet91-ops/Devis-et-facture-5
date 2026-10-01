<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Devis;
use App\Services\GestionDevis;
use App\Support\Metiers;
use App\Support\Montant;
use App\Support\Tva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DevisController extends Controller
{
    public function __construct(private GestionDevis $gestion) {}

    public function index(Request $request): View
    {
        $statut = $request->query('statut');

        $devis = Devis::with('client')
            ->statut($statut)
            ->when($request->filled('q'), fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('numero', 'like', '%'.addcslashes((string) $request->query('q'), '%_').'%')
                    ->orWhereHas('client', fn ($c) => $c->recherche((string) $request->query('q')));
            }))
            ->latest('updated_at')
            ->paginate(30)
            ->withQueryString();

        return view('devis.index', [
            'devis' => $devis,
            'statut' => $statut,
            'compteurs' => Devis::selectRaw('statut, count(*) as total')->groupBy('statut')->pluck('total', 'statut'),
            'total' => Devis::count(),
        ]);
    }

    /**
     * Nouveau devis : choisir le client (et l'adresse du chantier).
     */
    public function create(Request $request): View
    {
        $client = $request->filled('client') ? Client::findOrFail($request->integer('client')) : null;

        return view('devis.nouveau', [
            'client' => $client,
            'clients' => $client ? collect() : Client::query()
                ->when($request->filled('q'), fn ($q) => $q->recherche((string) $request->query('q')))
                ->orderByRaw('COALESCE(raison_sociale, nom)')->limit(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'chantier_id' => ['nullable', 'integer'],
            'objet' => ['nullable', 'string', 'max:200'],
        ]);

        $client = Client::findOrFail($donnees['client_id']);
        $chantier = ! empty($donnees['chantier_id']) ? $client->chantiers()->findOrFail($donnees['chantier_id']) : null;

        $devis = $this->gestion->creer($client, $chantier, $donnees['objet'] ?? null, $request->user()->id);

        return redirect()->route('devis.edit', $devis);
    }

    public function show(Devis $devis): View
    {
        $devis->load(['client', 'chantier', 'lignes', 'origine']);

        return view('devis.show', [
            'devis' => $devis,
            'detail' => $devis->detailTotaux(),
            'versions' => Devis::where(fn ($q) => $q->where('id', $devis->devis_origine_id ?? $devis->id)->orWhere('devis_origine_id', $devis->devis_origine_id ?? $devis->id))
                ->orderBy('version')->get(),
        ]);
    }

    public function edit(Devis $devis): View|RedirectResponse
    {
        if (! $devis->estModifiable()) {
            return redirect()->route('devis.show', $devis)->withErrors(['devis' => 'Ce devis a été envoyé : il ne se modifie plus. Faites une nouvelle version.']);
        }

        $devis->load(['client.chantiers', 'lignes']);

        return view('devis.editeur', [
            'devis' => $devis,
            'franchise' => Tva::estFranchise(),
            'tauxOptions' => Tva::options(),
            'unites' => (array) reglage('tva.unites'),
            'calculToiture' => Metiers::moduleActif('calculateur_toiture'),
        ]);
    }

    public function update(Request $request, Devis $devis): RedirectResponse
    {
        if (! $devis->estModifiable()) {
            return redirect()->route('devis.show', $devis)->withErrors(['devis' => 'Ce devis a été envoyé : il ne se modifie plus.']);
        }

        $entete = $request->validate([
            'objet' => ['nullable', 'string', 'max:200'],
            'chantier_id' => ['nullable', 'integer'],
            'validite_jours' => ['required', 'integer', 'min:1', 'max:365'],
            'acompte_pourcentage' => ['required', 'integer', 'min:0', 'max:100'],
            'date_debut_travaux' => ['nullable', 'date'],
            'duree_travaux' => ['nullable', 'string', 'max:100'],
            'dechets_estimation' => ['nullable', 'string', 'max:2000'],
            'remise_type' => ['nullable', 'in:pourcentage,montant'],
            'remise' => ['nullable', 'string', 'max:20'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'hors_etablissement' => ['nullable', 'boolean'],
            'urgence' => ['nullable', 'boolean'],
            'lignes' => ['nullable', 'array', 'max:300'],
        ], [], [
            'validite_jours' => 'validité', 'acompte_pourcentage' => 'acompte', 'date_debut_travaux' => 'date de début',
            'duree_travaux' => 'durée des travaux', 'dechets_estimation' => 'estimation des déchets',
        ]);

        $lecture = GestionDevis::lireLignes((array) $request->input('lignes', []));
        $remise = $this->lireRemise($request);
        if ($remise === null) {
            $lecture['erreurs']['remise'] = 'Remise illisible (exemple : 10 pour 10 %, ou 150,00 pour un montant).';
        }
        if ($lecture['erreurs']) {
            throw ValidationException::withMessages($lecture['erreurs']);
        }

        $chantier = ! empty($entete['chantier_id']) ? $devis->client->chantiers()->find($entete['chantier_id']) : null;

        $devis->fill([
            'objet' => $entete['objet'] ?? null,
            'chantier_id' => $chantier?->id,
            'validite_jours' => $entete['validite_jours'],
            'acompte_pourcentage' => $entete['acompte_pourcentage'],
            'date_debut_travaux' => $entete['date_debut_travaux'] ?? null,
            'duree_travaux' => $entete['duree_travaux'] ?? null,
            'dechets_estimation' => $entete['dechets_estimation'] ?? null,
            'remise_type' => $remise > 0 ? $entete['remise_type'] ?? null : null,
            'remise_valeur' => $remise,
            'conditions' => $entete['conditions'] ?? null,
            'hors_etablissement' => $request->boolean('hors_etablissement'),
            'urgence' => $request->boolean('urgence'),
        ])->save();

        $this->gestion->remplacerLignes($devis, $lecture['lignes']);

        return redirect()->route('devis.show', $devis)->with('statut', 'Devis enregistré.');
    }

    public function envoyer(Devis $devis): RedirectResponse
    {
        $this->gestion->marquerEnvoye($devis);

        return redirect()->route('devis.show', $devis)->with('statut', 'Devis '.$devis->numero.' marqué comme envoyé. Il ne se modifie plus.');
    }

    public function accepter(Devis $devis): RedirectResponse
    {
        $this->gestion->accepter($devis);

        return redirect()->route('devis.show', $devis)->with('statut', 'Devis accepté.');
    }

    public function refuser(Request $request, Devis $devis): RedirectResponse
    {
        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:1000']])['motif'] ?? null;
        $this->gestion->refuser($devis, $motif);

        return redirect()->route('devis.show', $devis)->with('statut', 'Devis marqué comme refusé.');
    }

    public function nouvelleVersion(Request $request, Devis $devis): RedirectResponse
    {
        $copie = $this->gestion->nouvelleVersion($devis, $request->user()->id);

        return redirect()->route('devis.edit', $copie)->with('statut', 'Version '.$copie->version.' créée : modifiez-la puis envoyez-la.');
    }

    public function dupliquer(Request $request, Devis $devis): RedirectResponse
    {
        $clientId = $request->filled('client_id') ? Client::findOrFail($request->integer('client_id'))->id : $devis->client_id;
        $copie = $this->gestion->dupliquer($devis, $clientId, $request->user()->id);

        return redirect()->route('devis.edit', $copie)->with('statut', 'Copie créée (brouillon).');
    }

    public function destroy(Devis $devis): RedirectResponse
    {
        if (! $devis->estModifiable()) {
            return back()->withErrors(['devis' => 'Seul un brouillon peut être supprimé. Un devis envoyé est conservé.']);
        }

        $devis->delete();

        return redirect()->route('devis.index')->with('statut', 'Brouillon mis à la corbeille.');
    }

    private function lireRemise(Request $request): ?int
    {
        $texte = trim((string) $request->input('remise'));
        if ($texte === '' || ! $request->filled('remise_type')) {
            return 0;
        }

        if ($request->input('remise_type') === 'pourcentage') {
            $valeur = Tva::lire(str_replace('%', '', $texte));

            return $valeur !== null && $valeur <= 10000 ? $valeur : null;
        }

        return Montant::lire($texte);
    }
}
