<?php

namespace App\Services;

use App\Support\Reglages;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Statistiques Jetpack d'un site WordPress.com : connexion OAuth en lecture seule,
 * jeton chiffré en base, mise à jour chaque nuit (jamais pendant l'affichage).
 */
class Jetpack
{
    public const AUTORISATION = 'https://public-api.wordpress.com/oauth2/authorize';

    public const JETON = 'https://public-api.wordpress.com/oauth2/token';

    public const API = 'https://public-api.wordpress.com/rest/v1.1/sites/';

    public function __construct(private Reglages $reglages) {}

    public function estReglable(): bool
    {
        return (string) reglage('site.jetpack_client_id') !== '' && $this->reglages->aSecret('site.jetpack_client_secret');
    }

    public function estConnecte(): bool
    {
        return $this->reglages->aSecret('site.jetpack_jeton') && (string) reglage('site.jetpack_site') !== '';
    }

    public function urlAutorisation(string $etat): string
    {
        return self::AUTORISATION.'?'.http_build_query([
            'client_id' => (string) reglage('site.jetpack_client_id'),
            'redirect_uri' => route('statistiques.jetpack.retour'),
            'response_type' => 'code',
            'scope' => 'stats',
            'state' => $etat,
        ]);
    }

    /**
     * Échange le code reçu contre le jeton (enregistré chiffré).
     */
    public function connecter(string $code): bool
    {
        $reponse = Http::asForm()->timeout(15)->post(self::JETON, [
            'client_id' => (string) reglage('site.jetpack_client_id'),
            'client_secret' => (string) $this->reglages->getSecret('site.jetpack_client_secret'),
            'redirect_uri' => route('statistiques.jetpack.retour'),
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);

        if (! $reponse->successful() || ! $reponse->json('access_token') || ! $reponse->json('blog_id')) {
            return false;
        }

        $this->reglages->setSecret('site.jetpack_jeton', (string) $reponse->json('access_token'));
        $this->reglages->set('site.jetpack_site', (string) $reponse->json('blog_id'));
        $this->reglages->set('site.jetpack_adresse', (string) $reponse->json('blog_url'));

        return true;
    }

    public function deconnecter(): void
    {
        $this->reglages->setSecret('site.jetpack_jeton', null);
        foreach (['site.jetpack_site', 'site.jetpack_adresse', 'site.jetpack_statistiques'] as $cle) {
            $this->reglages->oublier($cle);
        }
    }

    /**
     * Télécharge les statistiques des 30 derniers jours et les garde en cache.
     */
    public function mettreAJour(): bool
    {
        if (! $this->estConnecte()) {
            return false;
        }

        $site = rawurlencode((string) reglage('site.jetpack_site'));
        $client = Http::withToken((string) $this->reglages->getSecret('site.jetpack_jeton'))->acceptJson()->timeout(20);

        $visites = $client->get(self::API.$site.'/stats/visits', ['unit' => 'day', 'quantity' => 30]);
        if (! $visites->successful()) {
            return false;
        }
        $champs = (array) $visites->json('fields');
        $iDate = array_search('period', $champs, true);
        $iVues = array_search('views', $champs, true);
        $iVisiteurs = array_search('visitors', $champs, true);
        $jours = array_map(fn (array $l) => [
            'jour' => (string) ($l[$iDate === false ? 0 : $iDate] ?? ''),
            'vues' => (int) ($l[$iVues === false ? 1 : $iVues] ?? 0),
            'visiteurs' => (int) ($l[$iVisiteurs === false ? 2 : $iVisiteurs] ?? 0),
        ], (array) $visites->json('data'));

        $periode = ['period' => 'month', 'num' => 1];
        $pages = $this->premierJour($client->get(self::API.$site.'/stats/top-posts', $periode)->json('days'), 'postviews');
        $sources = $this->premierJour($client->get(self::API.$site.'/stats/referrers', $periode)->json('days'), 'groups');
        $clics = $this->premierJour($client->get(self::API.$site.'/stats/clicks', $periode)->json('days'), 'clicks');

        $this->reglages->set('site.jetpack_statistiques', [
            'jours' => $jours,
            'pages' => array_slice(array_map(fn ($p) => ['nom' => (string) ($p['title'] ?? ''), 'total' => (int) ($p['views'] ?? 0)], $pages), 0, 10),
            'sources' => array_slice(array_map(fn ($p) => ['nom' => (string) ($p['name'] ?? ''), 'total' => (int) ($p['total'] ?? 0)], $sources), 0, 10),
            'clics' => array_slice(array_map(fn ($p) => ['nom' => (string) ($p['name'] ?? ''), 'total' => (int) ($p['views'] ?? 0)], $clics), 0, 10),
            'maj' => now()->toIso8601String(),
        ]);

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function premierJour(mixed $jours, string $cle): array
    {
        $premier = is_array($jours) ? Arr::first($jours) : null;

        return is_array($premier) ? array_values((array) ($premier[$cle] ?? [])) : [];
    }
}
