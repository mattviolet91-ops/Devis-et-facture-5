<?php

namespace App\Http\Controllers;

use App\Models\Prestation;
use App\Services\CatalogueDepart;
use App\Support\Journal;
use App\Support\Montant;
use App\Support\Tva;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PrestationController extends Controller
{
    public function index(Request $request, CatalogueDepart $depart): View
    {
        $recherche = trim((string) $request->query('q'));

        $prestations = Prestation::query()
            ->when($recherche !== '', fn ($q) => $q->recherche($recherche))
            ->orderBy('categorie')->orderBy('nom')
            ->get();

        return view('catalogue.index', [
            'groupes' => $prestations->groupBy(fn (Prestation $p) => $p->categorie ?: 'Sans catégorie'),
            'recherche' => $recherche,
            'total' => Prestation::count(),
            'sansPrix' => Prestation::whereNull('prix_ht')->count(),
            'departEnAttente' => $depart->enAttente(),
        ]);
    }

    /**
     * Recherche pour l'ajout rapide dans un devis (JSON).
     */
    public function recherche(Request $request): JsonResponse
    {
        $prestations = Prestation::query()
            ->recherche((string) $request->query('q'))
            ->orderByDesc('utilisations')->orderBy('nom')
            ->limit(20)
            ->get();

        return response()->json($prestations->map->pourEditeur()->values());
    }

    public function create(): View
    {
        return view('catalogue.formulaire', ['prestation' => new Prestation(['unite' => 'u'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $prestation = Prestation::create($this->valider($request));
        Journal::ecrire('catalogue.creation', 'Prestation ajoutée : '.$prestation->nom, $prestation);

        return redirect()->route('catalogue.index')->with('statut', 'Prestation enregistrée.');
    }

    public function edit(Prestation $prestation): View
    {
        return view('catalogue.formulaire', ['prestation' => $prestation]);
    }

    public function update(Request $request, Prestation $prestation): RedirectResponse
    {
        $prestation->update($this->valider($request));

        return redirect()->route('catalogue.index')->with('statut', 'Prestation modifiée.');
    }

    public function destroy(Prestation $prestation): RedirectResponse
    {
        $prestation->delete();
        Journal::ecrire('catalogue.suppression', 'Prestation mise à la corbeille : '.$prestation->nom, $prestation);

        return redirect()->route('catalogue.index')->with('statut', 'Prestation mise à la corbeille.');
    }

    public function chargerDepart(CatalogueDepart $depart): RedirectResponse
    {
        $nombre = $depart->charger();

        return redirect()->route('catalogue.index')
            ->with('statut', "{$nombre} prestation(s) de départ ajoutée(s). Indiquez maintenant vos prix.");
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request): array
    {
        $prix = $request->filled('prix') ? Montant::lire((string) $request->input('prix')) : null;
        $request->merge(['prix_ht' => $prix, 'prix_lisible' => ! $request->filled('prix') || $prix !== null]);

        $donnees = $request->validate([
            'categorie' => ['nullable', 'string', 'max:80'],
            'nom' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unite' => ['required', Rule::in((array) reglage('tva.unites'))],
            'prix_lisible' => ['accepted'],
            'prix_ht' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'taux_tva' => ['nullable', Rule::in(array_keys(Tva::options()))],
        ], [
            'prix_lisible.accepted' => 'Le prix est illisible. Écrivez par exemple 12,50.',
        ], ['nom' => 'nom de la prestation', 'unite' => 'unité']);

        unset($donnees['prix_lisible']);
        $donnees['taux_tva'] = $request->filled('taux_tva') ? (int) $request->input('taux_tva') : null;

        return $donnees;
    }
}
