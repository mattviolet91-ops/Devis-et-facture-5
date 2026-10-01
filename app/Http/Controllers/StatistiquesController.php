<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Devis;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\Chiffres;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Statistiques (gérant) : chiffre d'affaires par mois, par provenance et par compte.
 */
class StatistiquesController extends Controller
{
    public const PERIODES = ['annee' => 'Cette année', '12mois' => '12 derniers mois', 'tout' => 'Depuis le début'];

    public function index(Request $request): View
    {
        $periode = array_key_exists((string) $request->query('periode'), self::PERIODES) ? (string) $request->query('periode') : 'annee';
        [$debut, $fin] = match ($periode) {
            '12mois' => [today()->subMonths(11)->startOfMonth(), today()->endOfDay()],
            'tout' => [Carbon::create(2000), today()->endOfDay()],
            default => [today()->startOfYear(), today()->endOfDay()],
        };

        // Chiffre d'affaires des 12 derniers mois.
        $mois = [];
        for ($m = today()->subMonths(11)->startOfMonth(); $m->lte(today()); $m->addMonth()) {
            $mois[] = ['libelle' => ucfirst($m->translatedFormat('M')), 'titre' => ucfirst($m->translatedFormat('F Y')), 'montant' => Chiffres::factureHt($m->copy(), $m->copy()->endOfMonth())];
        }

        return view('statistiques.index', [
            'periode' => $periode,
            'mois' => $mois,
            'provenances' => $this->parProvenance($debut, $fin),
            'comptes' => $this->parCompte($debut, $fin),
            'total' => Chiffres::factureHt($debut, $fin),
            'encaisse' => Chiffres::encaisse($debut, $fin),
        ]);
    }

    /**
     * @return list<array{nom: string, clients: int, devis: int, signes: int, signe_ht: int, facture_ht: int}>
     */
    private function parProvenance(Carbon $debut, Carbon $fin): array
    {
        $lignes = [];
        $clients = Client::get(['id', 'provenance'])->groupBy(fn (Client $c) => $c->provenance ?: 'Non renseignée');

        foreach ($clients as $provenance => $groupe) {
            $ids = $groupe->pluck('id')->all();
            $devis = Devis::whereIn('client_id', $ids)->whereNotNull('envoye_at')->whereBetween('envoye_at', [$debut, $fin]);
            $signes = Devis::whereIn('client_id', $ids)->where('statut', Devis::ACCEPTE)->whereBetween('accepte_at', [$debut, $fin]);
            $lignes[] = [
                'nom' => (string) $provenance,
                'clients' => count($ids),
                'devis' => $devis->count(),
                'signes' => $signes->count(),
                'signe_ht' => (int) $signes->sum('total_ht'),
                'facture_ht' => Chiffres::factureHt($debut, $fin, $ids),
            ];
        }

        usort($lignes, fn ($a, $b) => [$b['facture_ht'], $b['signe_ht'], $b['clients']] <=> [$a['facture_ht'], $a['signe_ht'], $a['clients']]);

        return $lignes;
    }

    /**
     * @return list<array{nom: string, role: string, devis: int, signes: int, signe_ht: int, rdv: int}>
     */
    private function parCompte(Carbon $debut, Carbon $fin): array
    {
        return User::orderBy('email')->get()->map(fn (User $u) => [
            'nom' => $u->email,
            'role' => $u->libelleRole(),
            'devis' => Devis::where('created_by', $u->id)->whereNotNull('envoye_at')->whereBetween('envoye_at', [$debut, $fin])->count(),
            'signes' => Devis::where('created_by', $u->id)->where('statut', Devis::ACCEPTE)->whereBetween('accepte_at', [$debut, $fin])->count(),
            'signe_ht' => (int) Devis::where('created_by', $u->id)->where('statut', Devis::ACCEPTE)->whereBetween('accepte_at', [$debut, $fin])->sum('total_ht'),
            'rdv' => RendezVous::where(fn ($q) => $q->where('user_id', $u->id)->orWhere(fn ($q) => $q->whereNull('user_id')->where('created_by', $u->id)))->whereBetween('debut', [$debut, $fin])->count(),
        ])->all();
    }
}
