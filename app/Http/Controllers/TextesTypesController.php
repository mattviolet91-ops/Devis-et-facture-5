<?php

namespace App\Http\Controllers;

use App\Support\Journal;
use App\Support\Reglages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Textes types : phrases toutes prêtes à insérer dans les devis, factures et emails.
 */
class TextesTypesController extends Controller
{
    public function __construct(private Reglages $reglages) {}

    public function index(): View
    {
        return view('reglages.textes', ['textes' => $this->textes()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $donnees = $this->valider($request);
        $textes = $this->textes();
        $textes[] = $donnees;
        $this->enregistrer($textes);

        Journal::ecrire('reglages.texte_type', 'Texte type ajouté : '.$donnees['titre']);

        return redirect()->route('reglages.textes')->with('statut', 'Texte ajouté.');
    }

    public function update(Request $request, int $index): RedirectResponse
    {
        $textes = $this->textes();
        abort_unless(isset($textes[$index]), 404);

        $textes[$index] = $this->valider($request);
        $this->enregistrer($textes);

        return redirect()->route('reglages.textes')->with('statut', 'Texte modifié.');
    }

    public function destroy(int $index): RedirectResponse
    {
        $textes = $this->textes();
        abort_unless(isset($textes[$index]), 404);

        $titre = $textes[$index]['titre'];
        unset($textes[$index]);
        $this->enregistrer(array_values($textes));

        Journal::ecrire('reglages.texte_type', 'Texte type supprimé : '.$titre);

        return redirect()->route('reglages.textes')->with('statut', 'Texte supprimé.');
    }

    /**
     * @return list<array{titre: string, texte: string}>
     */
    private function textes(): array
    {
        return array_values((array) reglage('textes_types', []));
    }

    /**
     * @param  list<array{titre: string, texte: string}>  $textes
     */
    private function enregistrer(array $textes): void
    {
        $this->reglages->set('textes_types', $textes);
    }

    /**
     * @return array{titre: string, texte: string}
     */
    private function valider(Request $request): array
    {
        return $request->validate([
            'titre' => ['required', 'string', 'max:100'],
            'texte' => ['required', 'string', 'max:5000'],
        ], [], ['titre' => 'titre', 'texte' => 'texte']);
    }
}
