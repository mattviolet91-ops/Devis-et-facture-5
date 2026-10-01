<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chantier;
use App\Models\Client;
use App\Models\Prestation;
use App\Services\DevisExpress;
use App\Services\GestionDevis;
use App\Support\Journal;
use App\Support\Montant;
use App\Support\Quantite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Petite API pour Claude : lecture et brouillons seulement. Rien n'est jamais envoyé au client.
 */
class ApiController extends Controller
{
    public function clients(Request $request): JsonResponse
    {
        $clients = Client::with('chantiers')->recherche((string) $request->query('q'))->limit(10)->get();

        return response()->json(['clients' => $clients->map(fn (Client $c) => [
            'id' => $c->id,
            'nom' => $c->nomComplet(),
            'type' => $c->type,
            'ville' => $c->ville,
            'chantiers' => $c->chantiers->map(fn (Chantier $ch) => ['id' => $ch->id, 'adresse' => $ch->titre()])->values(),
        ])->values()]);
    }

    public function prestations(Request $request): JsonResponse
    {
        $prestations = Prestation::recherche((string) $request->query('q'))->orderByDesc('utilisations')->orderBy('nom')->limit(20)->get();

        return response()->json(['prestations' => $prestations->map(fn (Prestation $p) => [
            'id' => $p->id, 'nom' => $p->nom, 'unite' => $p->unite, 'prix_ht' => Montant::formater($p->prix_ht), 'prix_ht_centimes' => $p->prix_ht,
        ])->values()]);
    }

    /**
     * Aperçu d'un devis express (une phrase), sans rien créer.
     */
    public function apercu(Request $request, DevisExpress $express): JsonResponse
    {
        $phrase = $request->validate(['phrase' => ['required', 'string', 'max:3000']])['phrase'];

        return response()->json($this->presenter($express->analyser($phrase)));
    }

    /**
     * Crée un devis BROUILLON (phrase du devis express, ou client et lignes).
     */
    public function creerDevis(Request $request, DevisExpress $express, GestionDevis $gestion): JsonResponse
    {
        $donnees = $request->validate([
            'phrase' => ['required_without:client_id', 'nullable', 'string', 'max:3000'],
            'client_id' => ['required_without:phrase', 'nullable', 'integer', 'exists:clients,id'],
            'chantier_id' => ['nullable', 'integer'],
            'objet' => ['nullable', 'string', 'max:200'],
            'lignes' => ['required_with:client_id', 'array', 'max:100'],
            'lignes.*.designation' => ['required', 'string', 'max:500'],
            'lignes.*.quantite' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'lignes.*.unite' => ['nullable', 'string', 'max:20'],
            'lignes.*.prix_ht' => ['required', 'numeric', 'min:0', 'max:10000000'],
        ]);

        if (! empty($donnees['phrase'])) {
            $resultat = $express->analyser($donnees['phrase']);
            if ($resultat['erreurs'] || ! $resultat['client']) {
                return response()->json(['erreur' => 'Phrase à corriger.'] + $this->presenter($resultat), 422);
            }
            $client = $resultat['client'];
            $lignes = $resultat['lignes'];
        } else {
            $client = Client::findOrFail($donnees['client_id']);
            $lignes = array_map(fn (array $l) => [
                'type' => 'ligne',
                'designation' => $l['designation'],
                'quantite' => (int) round(((float) $l['quantite']) * 1000),
                'unite' => $l['unite'] ?? 'u',
                'prix_unitaire_ht' => (int) round(((float) $l['prix_ht']) * 100),
            ], $donnees['lignes']);
        }

        $chantier = isset($donnees['chantier_id']) ? $client->chantiers()->find($donnees['chantier_id']) : null;
        $devis = $gestion->creer($client, $chantier, $donnees['objet'] ?? null, $request->user()->id);
        $gestion->remplacerLignes($devis, $lignes);
        $devis->refresh();
        Journal::ecrire('api.devis', 'Brouillon de devis créé par Claude (clé « '.$request->attributes->get('cle_api')->nom.' ») pour '.$client->nomComplet(), $devis);

        return response()->json([
            'id' => $devis->id,
            'statut' => 'brouillon',
            'client' => $client->nomComplet(),
            'total_ht' => Montant::formater($devis->total_ht),
            'total_ttc' => Montant::formater($devis->total_ttc),
            'adresse' => route('devis.edit', $devis),
            'message' => 'Brouillon créé. Rien n\'a été envoyé au client : vérifiez-le dans l\'application.',
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $resultat
     * @return array<string, mixed>
     */
    private function presenter(array $resultat): array
    {
        return [
            'client' => $resultat['client'] ? ['id' => $resultat['client']->id, 'nom' => $resultat['client']->nomComplet()] : null,
            'lignes' => array_map(fn (array $l) => [
                'designation' => $l['designation'] ?? '',
                'quantite' => isset($l['quantite']) ? $l['quantite'] / 1000 : null,
                'unite' => $l['unite'] ?? null,
                'prix_ht' => isset($l['prix_unitaire_ht']) ? Montant::formater((int) $l['prix_unitaire_ht']) : null,
                'total_ht' => Montant::formater(Quantite::total($l['quantite'] ?? 0, $l['prix_unitaire_ht'] ?? 0)),
            ], $resultat['lignes']),
            'total_ht' => Montant::formater((int) $resultat['total_ht']),
            'erreurs' => $resultat['erreurs'],
            'alertes' => $resultat['alertes'],
        ];
    }
}
