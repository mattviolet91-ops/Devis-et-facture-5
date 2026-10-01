<?php

namespace App\Http\Controllers;

use App\Services\Sauvegardes;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Réglages → Sauvegardes (gérant) : liste, téléchargement, sauvegarde immédiate.
 */
class SauvegardesController extends Controller
{
    public function index(Sauvegardes $sauvegardes): View
    {
        return view('reglages.sauvegardes', ['sauvegardes' => $sauvegardes->liste()]);
    }

    public function maintenant(Sauvegardes $sauvegardes): RedirectResponse
    {
        try {
            $sauvegardes->sauvegarderBase();
        } catch (\Throwable) {
            return back()->with('erreur', 'Sauvegarde impossible. Vérifiez l\'espace disque de l\'hébergement.');
        }
        Journal::ecrire('sauvegarde', 'Sauvegarde de la base faite à la main');

        return back()->with('statut', 'Sauvegarde faite. Téléchargez-la pour en garder une copie hors du serveur.');
    }

    public function telecharger(string $nom): StreamedResponse
    {
        abort_unless(Sauvegardes::nomValide($nom) && Storage::disk('local')->exists(Sauvegardes::DOSSIER.'/'.$nom), 404);
        Journal::ecrire('sauvegarde', 'Sauvegarde téléchargée : '.$nom);

        return Storage::disk('local')->download(Sauvegardes::DOSSIER.'/'.$nom, $nom, ['Cache-Control' => 'private, no-store']);
    }
}
