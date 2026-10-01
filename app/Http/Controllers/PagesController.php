<?php

namespace App\Http\Controllers;

use App\Support\FichiersReglages;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PagesController extends Controller
{
    public function plus(): View
    {
        return view('plus');
    }

    public function nouveau(): View
    {
        return view('nouveau');
    }

    /**
     * Rubriques prévues dans les prochaines étapes.
     */
    public function bientot(string $rubrique): View
    {
        $titres = [
            'factures' => 'Factures',
            'planning' => 'Planning',
        ];

        abort_unless(isset($titres[$rubrique]), 404);

        return view('bientot', ['titre' => $titres[$rubrique]]);
    }

    public function manifest(): JsonResponse
    {
        $nom = (string) reglage('identite.nom_commercial') ?: config('app.name');

        return response()->json([
            'name' => $nom,
            'short_name' => mb_strimwidth($nom, 0, 12, ''),
            'lang' => 'fr',
            'start_url' => '/accueil',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => (string) reglage('apparence.couleur_principale'),
            'icons' => FichiersReglages::aIcone() ? [
                ['src' => '/fichiers/icone-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/fichiers/icone-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
            ] : [
                ['src' => '/icons/icone-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => '/icons/icone-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => '/icons/icone-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
