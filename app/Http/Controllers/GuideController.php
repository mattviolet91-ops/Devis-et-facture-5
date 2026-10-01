<?php

namespace App\Http\Controllers;

use App\Support\BienDemarrer;
use App\Support\Guide;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $rubriques = Guide::pour($request->user());
        $recherche = trim((string) $request->query('q'));
        $ouverte = (string) $request->query('r');

        return view('guide.index', [
            'rubriques' => $recherche !== '' ? Guide::chercher($rubriques, $recherche) : $rubriques,
            'recherche' => $recherche,
            'ouverte' => array_key_exists($ouverte, $rubriques) ? $ouverte : null,
            'bienDemarrer' => $request->user()->estGerant() ? BienDemarrer::points($request->user()) : [],
        ]);
    }
}
