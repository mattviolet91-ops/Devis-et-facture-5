<?php

namespace App\Http\Controllers;

use App\Models\Devis;
use App\Models\EmailEnvoye;
use App\Models\Facture;
use App\Models\Rapport;
use App\Services\ConfigurationEmail;
use App\Services\EnvoiEmail;
use App\Services\GestionDevis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Envoi d'un devis ou d'une facture par email, à partir d'un modèle.
 */
class EnvoiController extends Controller
{
    public function __construct(private EnvoiEmail $emails) {}

    public function create(Request $request, string $type, int $id): View
    {
        $document = $this->document($request, $type, $id);
        $modeles = $this->modelesPour($document);

        $textes = [];
        foreach ($modeles as $modele) {
            $textes[$modele] = $this->emails->rediger($document, $modele);
        }

        return view('envoi.formulaire', [
            'document' => $document,
            'type' => $type,
            'modeles' => $modeles,
            'textes' => $textes,
            'modele' => in_array($request->query('modele'), $modeles, true) ? $request->query('modele') : $modeles[0],
            'configure' => app(ConfigurationEmail::class)->estConfiguree(),
            'historique' => EmailEnvoye::where('document_type', $document->getMorphClass())->where('document_id', $document->getKey())->latest()->get(),
        ]);
    }

    public function store(Request $request, string $type, int $id, GestionDevis $gestionDevis): RedirectResponse
    {
        $document = $this->document($request, $type, $id);
        $donnees = $request->validate([
            'destinataire' => ['required', 'email', 'max:150'],
            'modele' => ['required', Rule::in($this->modelesPour($document))],
            'sujet' => ['required', 'string', 'max:200'],
            'corps' => ['required', 'string', 'max:10000'],
            'joindre_pdf' => ['nullable', 'boolean'],
        ], ['destinataire.required' => 'Indiquez l\'email du client.'], ['destinataire' => 'email du client', 'corps' => 'message']);

        // Un brouillon de devis reçoit son numéro (et son PDF figé) au moment de l'envoi.
        if ($document instanceof Devis && $document->statut === Devis::BROUILLON) {
            $gestionDevis->marquerEnvoye($document);
            $document->refresh();
            $nouveaux = $this->emails->rediger($document, $donnees['modele']);
            $donnees['sujet'] = str_replace('{numero}', (string) $document->numero, $donnees['sujet']);
            $donnees['corps'] = strtr($donnees['corps'], ['{numero}' => (string) $document->numero, '{lien}' => $this->emails->variables($document)['{lien}']]);
            unset($nouveaux);
        }
        if ($document instanceof Facture && $document->statut === Facture::BROUILLON) {
            return back()->withErrors(['envoi' => 'Émettez d\'abord la facture : elle recevra son numéro.']);
        }

        $email = $this->emails->envoyer($document, $donnees['destinataire'], $donnees['sujet'], $donnees['corps'], $donnees['modele'], $request->boolean('joindre_pdf'), $request->user()->id);

        // On garde l'email du client s'il n'en avait pas.
        if (! $document->client->email) {
            $document->client->update(['email' => $donnees['destinataire']]);
        }

        if ($document instanceof Rapport && $email->statut === 'envoye') {
            $document->forceFill(['envoye_at' => now()])->save();
        }

        $route = match (true) {
            $document instanceof Devis => route('devis.show', $document),
            $document instanceof Rapport => route('rapports.show', $document),
            default => route('factures.show', $document),
        };

        return $email->statut === 'envoye'
            ? redirect()->to($route)->with('statut', 'Email envoyé à '.$donnees['destinataire'].'.')
            : redirect()->to($route)->withErrors(['envoi' => 'L\'email n\'est pas parti. Vérifiez Réglages → Emails (adresse Gmail et mot de passe d\'application).']);
    }

    private function document(Request $request, string $type, int $id): Model
    {
        return match ($type) {
            'devis' => Devis::with('client')->findOrFail($id),
            'facture' => $request->user()->estGerant() ? Facture::with('client')->findOrFail($id) : abort(403),
            'rapport' => Rapport::with('client')->findOrFail($id),
            default => abort(404),
        };
    }

    /**
     * @return list<string>
     */
    private function modelesPour(Model $document): array
    {
        if ($document instanceof Facture) {
            return $document->estEnRetard() ? ['relance', 'facture'] : ['facture', 'relance'];
        }

        if ($document instanceof Rapport) {
            return ['rapport'];
        }

        return $document->statut === Devis::ENVOYE ? ['devis', 'relance_devis'] : ['devis'];
    }
}
