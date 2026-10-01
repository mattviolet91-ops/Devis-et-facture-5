<?php

namespace App\Http\Controllers;

use App\Mail\EmailDeTest;
use App\Rules\Iban;
use App\Rules\Siret;
use App\Rules\TvaIntracom;
use App\Services\ConfigurationEmail;
use App\Support\FichiersReglages;
use App\Support\Journal;
use App\Support\Reglages;
use App\Support\SectionsReglages;
use App\Support\Tva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ReglagesController extends Controller
{
    public function __construct(private Reglages $reglages) {}

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
            'valeurs' => $this->valeursAffichees($definition),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $definition = SectionsReglages::section($section);
        abort_unless($definition, 404);

        $donnees = $this->nettoyer($request->all(), $definition);

        Validator::make(
            $donnees,
            SectionsReglages::regles($definition, $donnees),
            SectionsReglages::messages($definition),
            SectionsReglages::libelles($definition),
        )->after(fn ($validateur) => $this->verifierTaux($validateur, $definition, $donnees))
            ->validate();

        foreach ($definition['champs'] as $champ) {
            if (! empty($champ['verrouille'])) {
                continue;
            }
            $this->enregistrer($champ, $donnees, $request);
        }

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

    /**
     * Valeurs à afficher dans le formulaire (jamais les secrets).
     *
     * @return array<string, mixed>
     */
    private function valeursAffichees(array $definition): array
    {
        $valeurs = [];

        foreach ($definition['champs'] as $champ) {
            $valeur = reglage($champ['cle']);
            $valeurs[$champ['cle']] = match ($champ['type']) {
                'taux' => implode(' ; ', array_map(fn ($t) => Tva::formater((int) $t, false), (array) $valeur)),
                'lignes' => implode("\n", (array) $valeur),
                'secret' => $this->reglages->aSecret($champ['cle']),
                default => $champ['cle'] === 'documents.iban' ? Iban::formater((string) $valeur) : $valeur,
            };
        }

        return $valeurs;
    }

    /**
     * Retire espaces et points des numéros, met en majuscules, etc.
     *
     * @return array<string, mixed>
     */
    private function nettoyer(array $donnees, array $definition): array
    {
        foreach ($definition['champs'] as $champ) {
            $nom = SectionsReglages::nomChamp($champ['cle']);
            if (! isset($donnees[$nom]) || ! is_string($donnees[$nom])) {
                continue;
            }

            $donnees[$nom] = match ($champ['cle']) {
                'identite.siret' => Siret::nettoyer($donnees[$nom]),
                'identite.tva_intracom' => TvaIntracom::nettoyer($donnees[$nom]),
                'identite.code_ape' => strtoupper(str_replace('.', '', trim($donnees[$nom]))),
                'documents.iban' => Iban::nettoyer($donnees[$nom]),
                'documents.bic' => strtoupper(trim($donnees[$nom])),
                default => trim($donnees[$nom]),
            };

            if ($champ['type'] === 'couleur') {
                $donnees[$nom] = strtolower($donnees[$nom]);
            }
        }

        return $donnees;
    }

    private function verifierTaux($validateur, array $definition, array $donnees): void
    {
        if (! isset($donnees['tva__taux'])) {
            return;
        }

        $taux = Tva::lireListe((string) $donnees['tva__taux']);
        if ($taux === null) {
            $validateur->errors()->add('tva__taux', 'Un taux est illisible. Écrivez par exemple : 20 ; 10 ; 5,5 ; 0');

            return;
        }

        $defaut = (int) ($donnees['tva__taux_defaut'] ?? -1);
        if (($donnees['tva__regime'] ?? '') === 'assujetti' && ! in_array($defaut, $taux, true)) {
            $validateur->errors()->add('tva__taux_defaut', 'Le taux par défaut doit faire partie des taux utilisés.');
        }
    }

    private function enregistrer(array $champ, array $donnees, Request $request): void
    {
        $cle = $champ['cle'];
        $nom = SectionsReglages::nomChamp($cle);
        $valeur = $donnees[$nom] ?? null;

        switch ($champ['type']) {
            case 'case':
                $this->reglages->set($cle, (bool) $valeur);
                break;

            case 'entier':
                $this->reglages->set($cle, (int) $valeur);
                break;

            case 'taux':
                $this->reglages->set($cle, Tva::lireListe((string) $valeur));
                break;

            case 'taux_defaut':
                $this->reglages->set($cle, ($donnees['tva__regime'] ?? '') === 'franchise' ? 0 : (int) $valeur);
                break;

            case 'lignes':
                $lignes = array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/', (string) $valeur)))));
                $this->reglages->set($cle, $lignes);
                break;

            case 'secret':
                if ($request->boolean($nom.'__effacer')) {
                    $this->reglages->setSecret($cle, null);
                } elseif (is_string($valeur) && $valeur !== '') {
                    $this->reglages->setSecret($cle, str_replace(' ', '', $valeur));
                }
                break;

            case 'fichier':
                if ($request->boolean($nom.'__effacer')) {
                    FichiersReglages::supprimer($cle);
                } elseif ($request->hasFile($nom)) {
                    FichiersReglages::enregistrer($cle, $request->file($nom));
                }
                break;

            default:
                $this->reglages->set($cle, $valeur === null ? '' : (string) $valeur);
        }
    }
}
