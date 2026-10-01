<?php

namespace App\Http\Controllers;

use App\Models\VisiteSite;
use App\Services\Jetpack;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Statistiques → Site internet (gérant).
 */
class StatistiquesSiteController extends Controller
{
    public function index(Jetpack $jetpack): View
    {
        $debut = today()->subDays(29);
        $base = fn () => VisiteSite::where('jour', '>=', $debut->toDateString());

        $parJour = $base()->where('evenement', 'vue')
            ->select('jour', DB::raw('count(*) as vues'), DB::raw('count(distinct empreinte) as visites'))
            ->groupBy('jour')->get()
            ->keyBy(fn ($l) => substr((string) $l->jour, 0, 10));
        $jours = [];
        for ($j = $debut->copy(); $j->lte(today()); $j->addDay()) {
            $l = $parJour->get($j->toDateString());
            $jours[] = ['jour' => $j->copy(), 'vues' => (int) ($l->vues ?? 0), 'visites' => (int) ($l->visites ?? 0)];
        }

        // Une visite = une empreinte anonyme sur une journée.
        $compter = fn ($requete) => DB::query()->fromSub($requete->select('jour', 'empreinte')->distinct(), 'v')->count();
        $visites = $compter($base()->where('evenement', 'vue'));
        $contacts = $compter($base()->where('evenement', '!=', 'vue'));
        $grouper = fn (string $colonne, ?string $evenement = 'vue') => $base()
            ->when($evenement, fn ($q) => $q->where('evenement', $evenement))
            ->select($colonne.' as nom', DB::raw('count(*) as total'))
            ->groupBy($colonne)->orderByDesc('total')->limit(10)->get();

        return view('statistiques.site', [
            'actif' => (bool) reglage('site.compteur_actif'),
            'site' => (string) reglage('identite.site'),
            'jours' => $jours,
            'vues' => array_sum(array_column($jours, 'vues')),
            'visites' => $visites,
            'contacts' => $contacts,
            'pages' => $grouper('page'),
            'sources' => $grouper('source'),
            'appareils' => $grouper('appareil'),
            'boutons' => $base()->where('evenement', '!=', 'vue')->select('evenement as nom', DB::raw('count(*) as total'))->groupBy('evenement')->orderByDesc('total')->get(),
            'jetpackReglable' => $jetpack->estReglable(),
            'jetpackConnecte' => $jetpack->estConnecte(),
            'jetpack' => (array) reglage('site.jetpack_statistiques'),
        ]);
    }

    public function connecter(Request $request, Jetpack $jetpack): RedirectResponse
    {
        abort_unless($jetpack->estReglable(), 404);
        $etat = Str::random(40);
        $request->session()->put('jetpack_etat', $etat);

        return redirect()->away($jetpack->urlAutorisation($etat));
    }

    public function retour(Request $request, Jetpack $jetpack): RedirectResponse
    {
        $attendu = (string) $request->session()->pull('jetpack_etat');
        if ($attendu === '' || ! hash_equals($attendu, (string) $request->query('state')) || ! $request->filled('code')) {
            return redirect()->route('statistiques.site')->with('erreur', 'Connexion à Jetpack annulée ou expirée. Réessayez.');
        }

        try {
            $ok = $jetpack->connecter((string) $request->query('code')) && $jetpack->mettreAJour();
        } catch (\Throwable) {
            $ok = false;
        }

        return redirect()->route('statistiques.site')->with($ok ? 'statut' : 'erreur', $ok ? 'Jetpack connecté : statistiques à jour.' : 'WordPress.com a refusé la connexion. Vérifiez l\'identifiant et la clé secrète.');
    }

    public function deconnecter(Jetpack $jetpack): RedirectResponse
    {
        $jetpack->deconnecter();

        return redirect()->route('statistiques.site')->with('statut', 'Jetpack déconnecté.');
    }
}
