<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Devis;
use App\Models\EmailEnvoye;
use App\Models\Facture;
use App\Models\LienClient;
use App\Services\GestionDevis;
use App\Services\GestionFactures;
use App\Services\PdfFacture;
use App\Support\Montant;
use App\Support\Tva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Factures et avoirs (gérant seulement).
 */
class FactureController extends Controller
{
    public function __construct(private GestionFactures $gestion) {}

    public function index(Request $request): View
    {
        $filtre = (string) $request->query('filtre', '');

        $requete = Facture::with('client')->latest('updated_at');
        match ($filtre) {
            'brouillons' => $requete->where('statut', Facture::BROUILLON),
            'a-encaisser' => $requete->where('statut', Facture::EMISE)->where('type', '!=', Facture::AVOIR),
            'retard' => $requete->where('statut', Facture::EMISE)->where('type', '!=', Facture::AVOIR)->whereDate('date_echeance', '<', now()->toDateString()),
            'payees' => $requete->where('statut', Facture::PAYEE)->where('type', '!=', Facture::AVOIR),
            'avoirs' => $requete->where('type', Facture::AVOIR),
            default => null,
        };

        $aEncaisser = Facture::where('statut', Facture::EMISE)->where('type', '!=', Facture::AVOIR)->get();

        return view('factures.index', [
            'factures' => $requete->paginate(30)->withQueryString(),
            'filtre' => $filtre,
            'total' => Facture::count(),
            'resteTotal' => $aEncaisser->sum(fn (Facture $f) => $f->resteAPayer()),
            'enRetard' => $aEncaisser->filter->estEnRetard()->count(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('factures.nouvelle', [
            'clients' => Client::query()
                ->when($request->filled('q'), fn ($q) => $q->recherche((string) $request->query('q')))
                ->orderByRaw('COALESCE(raison_sociale, nom)')->limit(50)->get(),
            'devisAFacturer' => Devis::with('client')->where('statut', Devis::ACCEPTE)->latest('accepte_at')->limit(20)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Client::findOrFail($request->validate(['client_id' => ['required', 'integer', 'exists:clients,id']])['client_id']);
        $facture = $this->gestion->creerVide($client, $request->user()->id);

        return redirect()->route('factures.edit', $facture);
    }

    /**
     * Depuis un devis accepté : facture complète, acompte, situation ou solde.
     */
    public function depuisDevis(Request $request, Devis $devis): RedirectResponse
    {
        $donnees = $request->validate([
            'type' => ['required', 'in:facture,acompte,situation,solde'],
            'pourcentage' => ['nullable', 'string', 'max:10'],
        ]);

        $pourcentage = $request->filled('pourcentage') ? Tva::lire(str_replace('%', '', (string) $donnees['pourcentage'])) : null;
        if (in_array($donnees['type'], ['acompte', 'situation'], true) && $pourcentage === null) {
            throw ValidationException::withMessages(['pourcentage' => 'Indiquez un pourcentage, par exemple 30.']);
        }

        $facture = $this->gestion->depuisDevis($devis, $donnees['type'], $pourcentage, $request->user()->id);

        $retour = redirect()->route('factures.show', $facture)->with('statut', $facture->libelleType().' préparée (brouillon). Vérifiez-la puis émettez-la.');
        if ($rappel = GestionFactures::rappelSeptJours($devis)) {
            $retour->with('rappel', $rappel);
        }

        return $retour;
    }

    public function show(Facture $facture): View
    {
        $facture->load(['client', 'chantier', 'lignes', 'devis', 'origine', 'avoirs']);

        return view('factures.show', [
            'facture' => $facture,
            'detail' => $facture->detailTotaux(),
            'lien' => $facture->numero ? LienClient::where('document_type', $facture->getMorphClass())->where('document_id', $facture->id)->whereNull('revoque_at')->first() : null,
            'rappel' => GestionFactures::rappelSeptJours($facture->devis),
            'emails' => EmailEnvoye::where('document_type', $facture->getMorphClass())->where('document_id', $facture->id)->latest()->get(),
        ]);
    }

    public function edit(Facture $facture): View|RedirectResponse
    {
        if (! $facture->estModifiable()) {
            return redirect()->route('factures.show', $facture)->withErrors(['facture' => 'Une facture émise ne se modifie pas. Faites un avoir.']);
        }

        return view('factures.editeur', [
            'facture' => $facture->load(['client', 'lignes']),
            'franchise' => Tva::estFranchise(),
            'tauxOptions' => Tva::options(),
            'unites' => (array) reglage('tva.unites'),
        ]);
    }

    public function update(Request $request, Facture $facture): RedirectResponse
    {
        if (! $facture->estModifiable()) {
            return redirect()->route('factures.show', $facture)->withErrors(['facture' => 'Une facture émise ne se modifie pas. Faites un avoir.']);
        }

        $entete = $request->validate([
            'objet' => ['nullable', 'string', 'max:200'],
            'date_prestation' => ['nullable', 'date'],
            'delai_paiement_jours' => ['required', 'integer', 'min:0', 'max:60'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'remise_type' => ['nullable', 'in:pourcentage,montant'],
            'remise' => ['nullable', 'string', 'max:20'],
            'lignes' => ['nullable', 'array', 'max:300'],
        ], [], ['delai_paiement_jours' => 'délai de paiement', 'date_prestation' => 'date de la prestation']);

        $lecture = GestionDevis::lireLignes((array) $request->input('lignes', []), true);
        $remise = 0;
        if ($request->filled('remise') && $request->filled('remise_type')) {
            $remise = $request->input('remise_type') === 'pourcentage'
                ? Tva::lire(str_replace('%', '', (string) $request->input('remise')))
                : Montant::lire((string) $request->input('remise'));
            if ($remise === null) {
                $lecture['erreurs']['remise'] = 'Remise illisible.';
            }
        }
        if ($lecture['erreurs']) {
            throw ValidationException::withMessages($lecture['erreurs']);
        }

        $facture->fill([
            'objet' => $entete['objet'] ?? null,
            'date_prestation' => $entete['date_prestation'] ?? null,
            'delai_paiement_jours' => $entete['delai_paiement_jours'],
            'conditions' => $entete['conditions'] ?? null,
            'remise_type' => $remise > 0 ? $entete['remise_type'] : null,
            'remise_valeur' => (int) $remise,
        ])->save();
        $this->gestion->remplacerLignes($facture, $lecture['lignes']);

        return redirect()->route('factures.show', $facture)->with('statut', 'Facture enregistrée.');
    }

    public function emettre(Facture $facture): RedirectResponse
    {
        $this->gestion->emettre($facture);

        return redirect()->route('factures.show', $facture)->with('statut', $facture->libelleType().' '.$facture->numero.' émise. Elle ne se modifie plus.');
    }

    public function avoir(Request $request, Facture $facture): RedirectResponse
    {
        $donnees = $request->validate([
            'mode' => ['required', 'in:total,partiel'],
            'montant' => ['required_if:mode,partiel', 'nullable', 'string', 'max:20'],
            'motif' => ['required', 'string', 'max:500'],
        ], ['motif.required' => 'Indiquez le motif de l\'avoir.', 'montant.required_if' => 'Indiquez le montant de l\'avoir.']);

        $montant = $donnees['mode'] === 'partiel' ? Montant::lire((string) $donnees['montant']) : null;
        if ($donnees['mode'] === 'partiel' && $montant === null) {
            throw ValidationException::withMessages(['montant' => 'Montant illisible (exemple : 120,00).']);
        }

        $avoir = $this->gestion->creerAvoir($facture, $montant, $donnees['motif'], $request->user()->id);

        return redirect()->route('factures.show', $avoir)->with('statut', 'Avoir préparé (brouillon). Émettez-le pour qu\'il prenne effet.');
    }

    public function relancesAuto(Request $request, Facture $facture): RedirectResponse
    {
        $facture->forceFill(['relances_auto' => $request->boolean('relances_auto')])->save();

        return back()->with('statut', $facture->relances_auto ? 'Relances automatiques activées.' : 'Relances automatiques arrêtées.');
    }

    public function lien(Facture $facture): RedirectResponse
    {
        abort_if($facture->estModifiable(), 422);
        LienClient::pour($facture);

        return redirect()->to(route('factures.show', $facture).'#lien-client');
    }

    public function pdf(Facture $facture, PdfFacture $pdf): Response
    {
        return response($pdf->contenu($facture), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($facture->libelleType().' '.$facture->reference()).'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroy(Facture $facture): RedirectResponse
    {
        if (! $facture->estModifiable()) {
            return back()->withErrors(['facture' => 'Une facture émise ne se supprime jamais (conservation 10 ans). Faites un avoir.']);
        }

        $facture->delete();

        return redirect()->route('factures.index')->with('statut', 'Brouillon supprimé.');
    }
}
