<?php

namespace App\Http\Controllers;

use App\Services\EnregistreurReglages;
use App\Services\PdfExemple;
use App\Support\Configuration;
use App\Support\Journal;
use App\Support\Metiers;
use App\Support\Reglages;
use App\Support\SectionsReglages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Menu de configuration du premier lancement (gérant seulement).
 */
class ConfigurationController extends Controller
{
    /**
     * Champs de chaque écran (repris des Réglages), et ceux rendus obligatoires ici.
     *
     * @var array<string, array{cles: list<string>, obligatoires?: list<string>}>
     */
    private const CHAMPS = [
        'identite' => ['cles' => [
            'identite.nom_commercial', 'identite.forme_juridique', 'identite.capital', 'identite.siret', 'identite.code_ape',
            'identite.rcs_rm', 'identite.adresse', 'identite.code_postal', 'identite.ville', 'identite.telephone',
            'identite.email', 'identite.site',
        ], 'obligatoires' => ['identite.forme_juridique']],
        'tva' => ['cles' => ['tva.regime', 'identite.tva_intracom', 'tva.taux_defaut']],
        'assurance' => ['cles' => [
            'assurance.assureur', 'assurance.numero_contrat', 'assurance.date_debut', 'assurance.date_fin',
            'assurance.activites', 'assurance.zone', 'assurance.attestation',
        ], 'obligatoires' => ['assurance.assureur', 'assurance.numero_contrat', 'assurance.date_debut', 'assurance.date_fin', 'assurance.activites']],
        'documents' => ['cles' => [
            'numerotation.devis_prefixe', 'numerotation.devis_premier', 'numerotation.facture_prefixe', 'numerotation.facture_premier',
            'numerotation.avoir_prefixe', 'numerotation.avoir_premier', 'documents.validite_devis_jours', 'documents.delai_paiement_jours',
            'documents.acompte_pourcentage', 'documents.iban', 'documents.bic', 'identite.mediateur_nom', 'identite.mediateur_site',
        ], 'obligatoires' => ['documents.iban', 'identite.mediateur_nom']],
        'apparence' => ['cles' => ['apparence.logo', 'apparence.couleur_principale', 'apparence.couleur_accent']],
        'emails' => ['cles' => ['emails.adresse', 'emails.nom_expediteur', 'emails.mot_de_passe']],
        'cgv' => ['cles' => ['documents.cgv'], 'obligatoires' => ['documents.cgv']],
    ];

    public function __construct(private EnregistreurReglages $enregistreur, private Reglages $reglages) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('configuration.etape', Configuration::etapeAReprendre());
    }

    public function edit(string $etape): View
    {
        abort_unless(isset(Configuration::ETAPES[$etape]), 404);

        if ($etape === 'cgv' && trim((string) reglage('documents.cgv')) === '') {
            $this->reglages->set('documents.cgv', Metiers::cgvDeDepart((string) reglage('entreprise.metier', 'multiservice')));
        }

        $champs = self::champs($etape);

        return view('configuration.etape', [
            'etape' => $etape,
            'definition' => Configuration::ETAPES[$etape],
            'numero' => Configuration::numero($etape),
            'total' => count(Configuration::ETAPES),
            'precedente' => Configuration::precedente($etape),
            'champs' => $champs,
            'valeurs' => $this->enregistreur->valeursAffichees($champs),
            'metiers' => Metiers::tous(),
            'manquantes' => Configuration::manquantes(),
        ]);
    }

    public function update(Request $request, string $etape): RedirectResponse
    {
        abort_unless(isset(Configuration::ETAPES[$etape]) && $etape !== 'recapitulatif', 404);

        match ($etape) {
            'metier' => $this->enregistrerMetier($request),
            'cgv' => $this->enregistreur->enregistrer($request, self::champs('cgv'), ['cgv_relues' => ['accepted']], [
                'cgv_relues.accepted' => 'Cochez « J\'ai relu mes CGV » après les avoir lues et adaptées.',
            ]),
            default => $this->enregistreur->enregistrer($request, self::champs($etape)),
        };

        Configuration::marquerFaite($etape);
        Journal::ecrire('configuration.etape', 'Configuration : écran « '.Configuration::ETAPES[$etape]['titre'].' » enregistré');

        return redirect()->route('configuration.etape', Configuration::suivante($etape))
            ->with('statut', 'Enregistré.');
    }

    /**
     * Écran facultatif laissé pour plus tard.
     */
    public function plusTard(string $etape): RedirectResponse
    {
        abort_unless(Configuration::ETAPES[$etape]['facultatif'] ?? false, 404);

        return redirect()->route('configuration.etape', Configuration::suivante($etape));
    }

    /**
     * Le gérant passe le menu pour l'instant : l'application s'ouvre, les pages clients restent coupées.
     */
    public function passer(): RedirectResponse
    {
        $this->reglages->set('setup.passe_at', now()->toIso8601String());
        Journal::ecrire('configuration.passee', 'Menu de configuration passé pour l\'instant');

        return redirect()->route('accueil')
            ->with('statut', 'Vous pourrez terminer la configuration plus tard depuis le bandeau en haut de l\'accueil.');
    }

    public function terminer(): RedirectResponse
    {
        $manquantes = Configuration::manquantes();

        if ($manquantes !== []) {
            $titres = array_map(fn (string $e) => '« '.Configuration::ETAPES[$e]['titre'].' »', $manquantes);

            return back()->withErrors(['terminer' => 'Il reste à remplir : '.implode(', ', $titres).'.']);
        }

        $this->reglages->set('setup.completed_at', now()->toIso8601String());
        Journal::ecrire('configuration.terminee', 'Menu de configuration terminé');

        return redirect()->route('accueil')->with('statut', 'Configuration terminée. Tout reste modifiable dans Réglages.');
    }

    public function pdfExemple(PdfExemple $pdf): Response
    {
        return response($pdf->generer(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="devis-exemple.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function enregistrerMetier(Request $request): void
    {
        $donnees = $request->validate([
            'metier' => ['required', Rule::in(array_keys(Metiers::tous()))],
            'modules' => ['array'],
            'modules.*' => [Rule::in(array_keys(Metiers::MODULES))],
        ], ['metier.required' => 'Choisissez votre métier.']);

        $metier = Metiers::metier($donnees['metier']);
        $ancien = (string) reglage('entreprise.metier');

        $this->reglages->set('entreprise.metier', $donnees['metier']);
        $this->reglages->set('modules.actifs', array_values($donnees['modules'] ?? $metier['modules']));
        // Le catalogue de départ est chargé quand le module Catalogue est en place.
        $this->reglages->set('catalogue.depart_a_charger', $donnees['metier']);

        // CGV de départ du métier, si elles n'ont pas encore été relues.
        if ($ancien !== $donnees['metier'] && ! Configuration::estFaite('cgv')) {
            $this->reglages->set('documents.cgv', Metiers::cgvDeDepart($donnees['metier']));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function champs(string $etape): array
    {
        $definition = self::CHAMPS[$etape] ?? null;
        if (! $definition) {
            return [];
        }

        $tous = collect(SectionsReglages::toutes())->flatMap(fn (array $s) => $s['champs'])->keyBy('cle');

        return collect($definition['cles'])
            ->map(function (string $cle) use ($tous, $definition) {
                $champ = $tous[$cle];
                if (in_array($cle, $definition['obligatoires'] ?? [], true)) {
                    $champ['obligatoire'] = true;
                }

                return $champ;
            })
            ->values()
            ->all();
    }
}
