<?php

namespace App\Http\Controllers;

use App\Mail\EmailDeTest;
use App\Services\ConfigurationEmail;
use App\Services\EnregistreurReglages;
use App\Support\Journal;
use App\Support\SectionsReglages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ReglagesController extends Controller
{
    public function __construct(private EnregistreurReglages $enregistreur) {}

    public function index(): View
    {
        return view('reglages.index', ['sections' => SectionsReglages::toutes()]);
    }

    public function edit(string $section): View
    {
        $definition = SectionsReglages::section($section);
        abort_unless($definition, 404);

        return view('reglages.section', [
            'cle' => $section,
            'section' => $definition,
            'valeurs' => $this->enregistreur->valeursAffichees($definition['champs']),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $definition = SectionsReglages::section($section);
        abort_unless($definition, 404);

        $this->enregistreur->enregistrer($request, $definition['champs']);

        Journal::ecrire('reglages.modification', 'Réglages modifiés : '.$definition['titre']);

        return redirect()->route('reglages.edit', $section)->with('statut', 'Réglages enregistrés.');
    }

    public function testerEmail(Request $request, ConfigurationEmail $configuration): RedirectResponse
    {
        if (! $configuration->estConfiguree()) {
            return back()->withErrors(['test' => 'Indiquez d\'abord l\'adresse Gmail et le mot de passe d\'application, puis enregistrez.']);
        }

        $configuration->appliquer(forcer: true);
        $destinataire = $request->user()->email;

        try {
            Mail::to($destinataire)->send(new EmailDeTest);
        } catch (\Throwable $e) {
            Log::warning('Email de test non envoyé : '.$e->getMessage());

            return back()->withErrors(['test' => 'L\'envoi a échoué. Vérifiez l\'adresse Gmail et le mot de passe d\'application (16 lettres, sans espaces), puis réessayez.']);
        }

        Journal::ecrire('emails.test', 'Email de test envoyé à '.$destinataire);

        return back()->with('statut', "Email de test envoyé à {$destinataire}. Regardez votre boîte de réception (et les indésirables).");
    }
}
