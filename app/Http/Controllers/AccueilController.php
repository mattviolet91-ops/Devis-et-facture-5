<?php

namespace App\Http\Controllers;

use App\Models\Activite;
use App\Models\Demande;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Meteo;
use App\Support\Chiffres;
use App\Support\Montant;
use App\Support\Personnalisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccueilController extends Controller
{
    public function index(Request $request, Meteo $meteo): View
    {
        $user = $request->user();
        $blocs = Personnalisation::blocs($user);

        // Aujourd'hui : rendez-vous et chantiers du jour, avec la météo en cache.
        $aujourdhui = RendezVous::with(['client', 'chantier'])->entre(today(), today()->endOfDay())->orderBy('debut')->get();
        $previsions = $aujourdhui->mapWithKeys(fn (RendezVous $r) => [$r->id => $meteo->pour($r)->get(today()->toDateString())]);

        // Appels à passer : nouvelles demandes et devis sans réponse depuis 7 jours.
        $appels = Demande::where('statut', 'nouvelle')->whereNotNull('telephone')->latest('recue_at')->limit(5)->get()
            ->map(fn (Demande $d) => ['nom' => $d->nom ?: 'Demande', 'telephone' => $d->telephone, 'raison' => 'Nouvelle demande de devis', 'lien' => route('suivi.demande', $d)])
            ->merge(Devis::with('client')->where('statut', Devis::ENVOYE)->where('envoye_at', '<=', now()->subDays(7))->orderBy('envoye_at')->limit(5)->get()
                ->filter(fn (Devis $d) => $d->client?->telephone && $d->peutEtreSigne())
                ->map(fn (Devis $d) => ['nom' => $d->client->nomComplet(), 'telephone' => $d->client->telephone, 'raison' => 'Devis '.$d->reference().' sans réponse', 'lien' => route('devis.show', $d)]))
            ->values();

        return view('accueil', [
            'alertes' => $user->unreadNotifications()->latest()->limit(10)->get(),
            'blocs' => $blocs,
            'aujourdhui' => $aujourdhui,
            'previsions' => $previsions,
            'appels' => $appels,
            'taches' => in_array('taches', $blocs, true) ? $this->taches($user) : [],
            'chiffres' => in_array('chiffres', $blocs, true) ? $this->chiffres($user) : [],
            'activite' => in_array('activite', $blocs, true)
                ? Activite::with('user')->when(! $user->estGerant(), fn ($q) => $q->where('user_id', $user->id))->latest('id')->limit(6)->get()
                : collect(),
        ]);
    }

    public function personnaliser(Request $request): View
    {
        return view('accueil-personnaliser', [
            'blocs' => Personnalisation::blocs($request->user()),
            'boutons' => Personnalisation::boutons($request->user()),
            'permis' => Personnalisation::boutonsPermis($request->user()),
        ]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $permis = array_keys(Personnalisation::boutonsPermis($request->user()));
        $donnees = $request->validate([
            'blocs' => ['nullable', 'array'],
            'blocs.*' => [Rule::in(array_keys(Personnalisation::BLOCS))],
            'ordre' => ['nullable', 'array'],
            'ordre.*' => ['nullable', 'integer', 'min:1', 'max:9'],
            'bouton_1' => ['required', Rule::in($permis)],
            'bouton_2' => ['required', Rule::in($permis), 'different:bouton_1'],
        ], ['bouton_2.different' => 'Choisissez deux boutons différents.']);

        $ordre = (array) ($donnees['ordre'] ?? []);
        $blocs = collect($donnees['blocs'] ?? [])->sortBy(fn (string $b) => (int) ($ordre[$b] ?? 9))->values()->all();

        $user = $request->user();
        $preferences = (array) $user->preferences;
        data_set($preferences, 'accueil.blocs', $blocs);
        data_set($preferences, 'accueil.sans_astuce', ! in_array('astuce', $blocs, true));
        data_set($preferences, 'barre.boutons', [$donnees['bouton_1'], $donnees['bouton_2']]);
        $user->forceFill(['preferences' => $preferences])->save();

        return redirect()->route('accueil')->with('statut', 'Accueil personnalisé.');
    }

    public function lireToutesAlertes(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('accueil');
    }

    public function lireAlerte(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return redirect()->route('accueil');
    }

    /**
     * @return list<array{texte: string, nombre: int, lien: string}>
     */
    private function taches(User $user): array
    {
        $taches = [
            ['texte' => 'Nouvelles demandes de devis', 'nombre' => Demande::where('statut', 'nouvelle')->count(), 'lien' => route('suivi')],
            ['texte' => 'Devis en brouillon', 'nombre' => Devis::where('statut', Devis::BROUILLON)->count(), 'lien' => route('devis.index', ['statut' => 'brouillon'])],
            ['texte' => 'Devis sans réponse depuis 7 jours', 'nombre' => Devis::where('statut', Devis::ENVOYE)->where('envoye_at', '<=', now()->subDays(7))->count(), 'lien' => route('suivi')],
            ['texte' => 'Chantiers acceptés à planifier', 'nombre' => Devis::where('statut', Devis::ACCEPTE)->whereDoesntHave('rendezVous', fn ($q) => $q->where('type', 'chantier'))->count(), 'lien' => route('planning.a-planifier')],
        ];
        if ($user->estGerant()) {
            $retard = Facture::where('statut', Facture::EMISE)->whereDate('date_echeance', '<', today())->get()->filter(fn (Facture $f) => $f->resteAPayer() > 0)->count();
            $taches[] = ['texte' => 'Factures en retard de paiement', 'nombre' => $retard, 'lien' => route('factures.index', ['filtre' => 'retard'])];
        }

        return array_values(array_filter($taches, fn (array $t) => $t['nombre'] > 0));
    }

    /**
     * Chiffres du mois. Le commercial ne voit jamais le chiffre d'affaires.
     *
     * @return list<array{libelle: string, valeur: string}>
     */
    private function chiffres(User $user): array
    {
        $debut = today()->startOfMonth();
        $signes = Devis::where('statut', Devis::ACCEPTE)->where('accepte_at', '>=', $debut);
        $envoyes = Devis::whereNotNull('envoye_at')->where('envoye_at', '>=', $debut)->count();

        if (! $user->estGerant()) {
            return [
                ['libelle' => 'Devis envoyés ce mois', 'valeur' => (string) $envoyes],
                ['libelle' => 'Devis signés ce mois', 'valeur' => (string) $signes->count()],
                ['libelle' => 'Rendez-vous cette semaine', 'valeur' => (string) RendezVous::entre(today()->startOfWeek(), today()->endOfWeek())->count()],
            ];
        }

        $aEncaisser = Facture::where('statut', Facture::EMISE)->where('type', '!=', Facture::AVOIR)->get()->sum(fn (Facture $f) => max(0, $f->resteAPayer()));

        return [
            ['libelle' => 'Facturé ce mois (HT)', 'valeur' => Montant::formater(Chiffres::factureHt($debut, today()->endOfMonth()))],
            ['libelle' => 'Encaissé ce mois', 'valeur' => Montant::formater(Chiffres::encaisse($debut, today()->endOfMonth()))],
            ['libelle' => 'Devis signés ce mois', 'valeur' => $signes->count().' · '.Montant::formater((int) (clone $signes)->sum('total_ht')).' HT'],
            ['libelle' => 'Reste à encaisser', 'valeur' => Montant::formater($aEncaisser)],
        ];
    }
}
