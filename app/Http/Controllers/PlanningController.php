<?php

namespace App\Http\Controllers;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Photo;
use App\Models\RendezVous;
use App\Models\User;
use App\Services\Meteo;
use App\Support\Calendrier;
use App\Support\Journal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function index(Request $request, Meteo $meteo): View
    {
        $vue = $request->query('vue') === 'mois' ? 'mois' : 'semaine';
        try {
            $date = $request->filled('date') ? Carbon::createFromFormat('Y-m-d', (string) $request->query('date'))->startOfDay() : today();
        } catch (\Throwable) {
            $date = today();
        }

        if ($vue === 'mois') {
            $debut = $date->copy()->startOfMonth()->startOfWeek();
            $fin = $date->copy()->endOfMonth()->endOfWeek();
        } else {
            $debut = $date->copy()->startOfWeek();
            $fin = $debut->copy()->addDays(6)->endOfDay();
        }

        $rendezVous = RendezVous::with(['client', 'chantier', 'user'])->entre($debut, $fin)->orderBy('debut')->get();

        $jours = [];
        for ($jour = $debut->copy(); $jour->lte($fin); $jour->addDay()) {
            $jours[$jour->toDateString()] = [
                'date' => $jour->copy(),
                'rdv' => $rendezVous->filter(fn (RendezVous $r) => $r->debut->copy()->startOfDay()->lte($jour) && $r->fin->gte($jour))->values(),
            ];
        }

        // Météo déjà en cache (aucun appel à Internet ici).
        $previsions = [];
        if ($vue === 'semaine') {
            foreach ($rendezVous as $rdv) {
                $previsions[$rdv->id] = $meteo->pour($rdv);
            }
        }

        return view('planning.index', [
            'vue' => $vue,
            'date' => $date,
            'jours' => $jours,
            'previsions' => $previsions,
            'precedent' => $vue === 'mois' ? $date->copy()->startOfMonth()->subMonth() : $debut->copy()->subWeek(),
            'suivant' => $vue === 'mois' ? $date->copy()->startOfMonth()->addMonth() : $debut->copy()->addWeek(),
            'aPlanifier' => $this->devisAPlanifier()->count(),
        ]);
    }

    public function aPlanifier(): View
    {
        return view('planning.a-planifier', ['devis' => $this->devisAPlanifier()->with(['client', 'chantier'])->get()]);
    }

    public function create(Request $request): View
    {
        $rdv = new RendezVous(['type' => 'rdv', 'user_id' => $request->user()->id]);
        $jour = $request->filled('date') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('date')) ? Carbon::parse((string) $request->query('date')) : today()->addDay();
        $rdv->debut = $jour->copy()->setTime(9, 0);
        $rdv->fin = $jour->copy()->setTime(10, 0);

        if ($request->filled('client')) {
            $rdv->client_id = Client::findOrFail((int) $request->query('client'))->id;
        }
        if ($request->filled('devis')) {
            $devis = Devis::findOrFail((int) $request->query('devis'));
            $rdv->fill([
                'type' => 'chantier', 'titre' => $devis->objet ?: 'Chantier '.$devis->reference(), 'devis_id' => $devis->id,
                'client_id' => $devis->client_id, 'chantier_id' => $devis->chantier_id, 'journee_entiere' => true,
            ]);
            $rdv->debut = $jour->copy()->startOfDay();
            $rdv->fin = $jour->copy()->endOfDay();
        }

        return view('planning.formulaire', $this->donneesFormulaire($rdv));
    }

    public function store(Request $request): RedirectResponse
    {
        $rdv = RendezVous::create($this->valider($request) + ['created_by' => $request->user()->id]);
        Journal::ecrire('planning.creation', ($rdv->estChantier() ? 'Chantier planifié : ' : 'Rendez-vous ajouté : ').$rdv->titre, $rdv);

        return redirect()->route('planning.show', $rdv)->with('statut', 'Enregistré dans le planning.');
    }

    public function show(RendezVous $rdv, Meteo $meteo): View
    {
        $rdv->load(['client', 'chantier', 'devis', 'user']);

        return view('planning.show', [
            'rdv' => $rdv,
            'previsions' => $meteo->pour($rdv),
            'photos' => Photo::where('rendez_vous_id', $rdv->id)->orderBy('id')->get(),
        ]);
    }

    public function edit(RendezVous $rdv): View
    {
        return view('planning.formulaire', $this->donneesFormulaire($rdv));
    }

    public function update(Request $request, RendezVous $rdv): RedirectResponse
    {
        $donnees = $this->valider($request);
        // Nouvelle date : les rappels repartent de zéro.
        if (! $rdv->debut->equalTo($donnees['debut'])) {
            $rdv->rappels_envoyes = [];
        }
        $rdv->update($donnees);

        return redirect()->route('planning.show', $rdv)->with('statut', 'Modifications enregistrées.');
    }

    public function fait(RendezVous $rdv): RedirectResponse
    {
        $rdv->update(['fait' => ! $rdv->fait]);

        return back()->with('statut', $rdv->fait ? 'Marqué comme fait.' : 'Remis à faire.');
    }

    public function provenance(Request $request, RendezVous $rdv): RedirectResponse
    {
        abort_unless($rdv->client, 404);
        $donnees = $request->validate([
            'provenance' => ['nullable', 'string', 'max:100'],
            'provenance_detail' => ['nullable', 'string', 'max:200'],
        ]);
        $rdv->client->update($donnees);

        return back()->with('statut', 'Provenance du client enregistrée.');
    }

    public function destroy(RendezVous $rdv): RedirectResponse
    {
        $rdv->delete();
        Journal::ecrire('planning.suppression', 'Retiré du planning : '.$rdv->titre, $rdv);

        return redirect()->route('planning.index', ['date' => $rdv->debut->toDateString()])->with('statut', 'Mis à la corbeille.');
    }

    public function ics(RendezVous $rdv): Response
    {
        return $this->reponseIcs(Calendrier::ics(collect([$rdv->load(['client', 'chantier'])])), 'rendez-vous-'.$rdv->debut->format('Y-m-d').'.ics');
    }

    /**
     * Tout le planning à venir (90 jours), à importer dans l'agenda du téléphone.
     */
    public function exporter(): Response
    {
        $rdv = RendezVous::with(['client', 'chantier'])->entre(today(), today()->addDays(90)->endOfDay())->orderBy('debut')->get();

        return $this->reponseIcs(Calendrier::ics($rdv), 'planning.ics');
    }

    private function reponseIcs(string $contenu, string $nom): Response
    {
        return response($contenu, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$nom.'"',
        ]);
    }

    /**
     * Devis acceptés dont le chantier n'est pas encore au planning.
     */
    private function devisAPlanifier()
    {
        return Devis::where('statut', Devis::ACCEPTE)
            ->whereDoesntHave('rendezVous', fn ($q) => $q->where('type', 'chantier'))
            ->orderBy('accepte_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function donneesFormulaire(RendezVous $rdv): array
    {
        return [
            'rdv' => $rdv,
            'clients' => Client::orderBy('nom')->orderBy('raison_sociale')->get()->mapWithKeys(fn (Client $c) => [$c->id => $c->nomComplet()])->sort(),
            'chantiers' => Chantier::with('client')->get()->mapWithKeys(fn (Chantier $c) => [$c->id => $c->client?->nomComplet().' — '.$c->titre()])->sort(),
            'personnes' => User::where('is_active', true)->orderBy('email')->pluck('email', 'id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request): array
    {
        $donnees = $request->validate([
            'type' => ['required', Rule::in(['rdv', 'chantier'])],
            'titre' => ['required', 'string', 'max:200'],
            'date_debut' => ['required', 'date_format:Y-m-d'],
            'heure_debut' => ['nullable', 'date_format:H:i'],
            'date_fin' => ['nullable', 'date_format:Y-m-d'],
            'heure_fin' => ['nullable', 'date_format:H:i'],
            'journee_entiere' => ['nullable', 'boolean'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'chantier_id' => ['nullable', 'integer', 'exists:chantiers,id'],
            'devis_id' => ['nullable', 'integer', 'exists:devis,id'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'rappel_client_jours' => ['nullable', Rule::in(['', '1', '2'])],
        ], [], [
            'titre' => 'objet', 'date_debut' => 'date', 'heure_debut' => 'heure de début', 'date_fin' => 'date de fin',
            'heure_fin' => 'heure de fin', 'client_id' => 'client', 'chantier_id' => 'adresse de chantier', 'user_id' => 'personne',
        ]);

        $journee = $request->boolean('journee_entiere') || $donnees['type'] === 'chantier' && ! $request->filled('heure_debut');
        $dateFin = $donnees['date_fin'] ?? $donnees['date_debut'];

        if ($journee) {
            $debut = Carbon::parse($donnees['date_debut'])->startOfDay();
            $fin = Carbon::parse($dateFin)->setTime(23, 59, 59);
        } else {
            $debut = Carbon::parse($donnees['date_debut'].' '.($donnees['heure_debut'] ?? '09:00'));
            $fin = $request->filled('heure_fin') ? Carbon::parse($dateFin.' '.$donnees['heure_fin']) : $debut->copy()->addHour();
        }

        if ($fin->lt($debut)) {
            throw ValidationException::withMessages(['date_fin' => 'La fin doit être après le début.']);
        }
        if ($debut->diffInDays($fin) > 62) {
            throw ValidationException::withMessages(['date_fin' => 'Un chantier dure au plus 2 mois dans le planning. Découpez-le si besoin.']);
        }

        return [
            'type' => $donnees['type'],
            'titre' => $donnees['titre'],
            'debut' => $debut,
            'fin' => $fin,
            'journee_entiere' => $journee,
            'lieu' => $donnees['lieu'] ?? null,
            'client_id' => $donnees['client_id'] ?? null,
            'chantier_id' => $donnees['chantier_id'] ?? null,
            'devis_id' => $donnees['devis_id'] ?? null,
            'user_id' => $donnees['user_id'] ?? null,
            'notes' => $donnees['notes'] ?? null,
            'rappel_client_jours' => ($donnees['rappel_client_jours'] ?? '') !== '' ? (int) $donnees['rappel_client_jours'] : null,
        ];
    }
}
