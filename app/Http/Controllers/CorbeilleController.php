<?php

namespace App\Http\Controllers;

use App\Support\Corbeille;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CorbeilleController extends Controller
{
    public function index(): View
    {
        return view('corbeille', ['elements' => Corbeille::elements()]);
    }

    public function restaurer(string $type, int $id): RedirectResponse
    {
        $definition = Corbeille::type($type);
        abort_unless($definition, 404);

        $modele = $definition['classe']::onlyTrashed()->findOrFail($id);
        $modele->restore();

        Journal::ecrire('corbeille.restauration', $definition['libelle'].' restauré : '.Corbeille::libelle($modele), $modele);

        return redirect()->route('corbeille')->with('statut', 'Élément remis en place.');
    }
}
