<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\EmailRecu;
use App\Models\Facture;
use App\Models\RendezVous;
use App\Services\BoiteEmails;
use App\Services\EnvoiEmail;
use App\Services\LectureDemandes;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suivi commercial : demandes reçues, devis à relancer, avis Google, entretiens.
 */
class SuiviController extends Controller
{
    public function index(): View
    {
        return view('suivi.index', [
            'demandes' => Demande::where('statut', 'nouvelle')->latest('recue_at')->get(),
            'devis' => $this->devisARelancer(),
            'avis' => reglage('suivi.lien_avis') ? $this->clientsPourAvis() : collect(),
            'entretiens' => $this->clientsPourEntretien(),
            'lienFormulaire' => reglage('suivi.formulaire_actif') ? route('demande.create') : null,
        ]);
    }

    public function demande(Demande $demande): View
    {
        return view('suivi.demande', ['demande' => $demande]);
    }

    public function statutDemande(Request $request, Demande $demande): RedirectResponse
    {
        $demande->update($request->validate(['statut' => ['required', Rule::in(array_keys(Demande::STATUTS))]]));

        return redirect()->route('suivi')->with('statut', 'Demande « '.$demande->libelleStatut().' ».');
    }

    /**
     * Crée le client à partir de la demande (nom, téléphone, email, ville).
     */
    public function creerClient(Request $request, Demande $demande): RedirectResponse
    {
        $morceaux = preg_split('/\s+/', trim((string) $demande->nom), 2);
        $client = Client::create([
            'type' => Client::PARTICULIER,
            'prenom' => count($morceaux) > 1 ? $morceaux[0] : null,
            'nom' => count($morceaux) > 1 ? $morceaux[1] : ($morceaux[0] ?: 'À compléter'),
            'telephone' => $demande->telephone,
            'email' => $demande->email,
            'ville' => $demande->ville,
            'provenance' => 'Site internet',
            'created_by' => $request->user()->id,
        ]);
        if ($demande->message) {
            $client->notes()->create(['texte' => 'Demande reçue le '.$demande->recue_at->format('d/m/Y')." :\n".$demande->message, 'user_id' => $request->user()->id]);
        }
        $demande->update(['client_id' => $client->id, 'statut' => 'traitee']);
        Journal::ecrire('client.creation', 'Client créé depuis une demande : '.$client->nomComplet(), $client);

        return redirect()->route('clients.edit', $client)->with('statut', 'Client créé. Vérifiez et complétez sa fiche.');
    }

    public function avis(Request $request, Client $client, EnvoiEmail $emails): RedirectResponse
    {
        abort_unless(reglage('suivi.lien_avis') && $client->email, 404);
        $email = $emails->envoyerAuClient($client, 'avis', $request->user()->id);
        $client->forceFill(['avis_demande_at' => now()])->save();

        return back()->with($email->statut === 'envoye' ? 'statut' : 'erreur', $email->statut === 'envoye'
            ? 'Demande d\'avis envoyée à '.$client->nomComplet().'.'
            : 'L\'email n\'est pas parti. Vérifiez Réglages → Emails.');
    }

    public function entretien(Request $request, Client $client, EnvoiEmail $emails): RedirectResponse
    {
        if ($request->boolean('email') && $client->email) {
            $email = $emails->envoyerAuClient($client, 'entretien', $request->user()->id);
            if ($email->statut !== 'envoye') {
                return back()->with('erreur', 'L\'email n\'est pas parti. Vérifiez Réglages → Emails.');
            }
        }
        $client->forceFill(['entretien_propose_at' => now()])->save();

        return back()->with('statut', 'Entretien proposé à '.$client->nomComplet().'.');
    }

    public function emails(BoiteEmails $boite): View
    {
        return view('suivi.emails', [
            'emails' => EmailRecu::orderByDesc('recu_at')->limit(LectureDemandes::GARDER)->get(),
            'configuree' => $boite->estConfiguree(),
        ]);
    }

    public function actualiser(BoiteEmails $boite): RedirectResponse
    {
        abort_unless($boite->estConfiguree(), 404);
        $code = Artisan::call('app:lire-emails');

        return back()->with($code === 0 ? 'statut' : 'erreur', $code === 0 ? 'Emails mis à jour.' : 'La boîte de réception ne répond pas. Vérifiez Réglages → Emails.');
    }

    /**
     * @return Collection<int, Devis>
     */
    private function devisARelancer(): Collection
    {
        return Devis::with('client')
            ->where('statut', Devis::ENVOYE)
            ->where('envoye_at', '<=', now()->subDays(7))
            ->orderBy('envoye_at')
            ->get()
            ->filter(fn (Devis $d) => $d->peutEtreSigne())
            ->values();
    }

    /**
     * Clients avec un chantier fini ou une facture réglée ces 60 derniers jours, sans demande d'avis.
     *
     * @return Collection<int, Client>
     */
    private function clientsPourAvis(): Collection
    {
        $depuis = now()->subDays(60);
        $ids = RendezVous::where('type', 'chantier')->where('fait', true)->where('fin', '>=', $depuis)->pluck('client_id')
            ->merge(Facture::where('statut', Facture::PAYEE)->where('updated_at', '>=', $depuis)->pluck('client_id'))
            ->filter()->unique();

        return Client::whereIn('id', $ids)->whereNull('avis_demande_at')->whereNotNull('email')->orderBy('nom')->get();
    }

    /**
     * Clients dont le dernier chantier date de plus de N mois (réglage), pas encore relancés.
     *
     * @return Collection<int, array{client: Client, dernier: Carbon}>
     */
    private function clientsPourEntretien(): Collection
    {
        $mois = max(1, (int) reglage('suivi.entretien_mois'));
        $limite = now()->subMonths($mois);

        $derniers = RendezVous::whereNotNull('client_id')->where('fait', true)
            ->selectRaw('client_id, max(fin) as dernier')
            ->groupBy('client_id')
            ->get()
            ->filter(fn ($l) => Carbon::parse($l->dernier)->lte($limite));

        $aVenir = RendezVous::where('debut', '>=', now())->pluck('client_id')->filter()->all();

        return Client::whereIn('id', $derniers->pluck('client_id'))
            ->whereNotIn('id', $aVenir)
            ->where(fn ($q) => $q->whereNull('entretien_propose_at')->orWhere('entretien_propose_at', '<=', $limite))
            ->get()
            ->map(fn (Client $c) => ['client' => $c, 'dernier' => Carbon::parse($derniers->firstWhere('client_id', $c->id)->dernier)])
            ->sortBy('dernier')
            ->values();
    }
}
