<?php

namespace App\Services;

use App\Models\VisiteSite;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Compteur de visites du site de l'entreprise, respectueux de la vie privée :
 * aucun cookie, aucune adresse IP enregistrée, empreinte anonyme qui change chaque jour.
 */
class CompteurSite
{
    public const ROBOTS = '/bot|crawl|spider|slurp|bingpreview|headless|lighthouse|pingdom|uptime|monitor|curl|wget|python|php|java\/|facebookexternalhit|preview/i';

    public function enregistrer(Request $request): bool
    {
        $agent = (string) $request->userAgent();
        if ($agent === '' || preg_match(self::ROBOTS, $agent)) {
            return false;
        }

        $donnees = json_decode((string) $request->getContent(), true);
        if (! is_array($donnees)) {
            return false;
        }

        $evenement = (string) ($donnees['e'] ?? 'vue');
        if (! array_key_exists($evenement, VisiteSite::EVENEMENTS)) {
            return false;
        }

        VisiteSite::create([
            'jour' => today()->toDateString(),
            'empreinte' => $this->empreinte($request),
            'evenement' => $evenement,
            'page' => Str::limit($this->chemin((string) ($donnees['p'] ?? '/')), 250, ''),
            'source' => $this->source((string) ($donnees['r'] ?? '')),
            'appareil' => $this->appareil($agent),
        ]);

        return true;
    }

    /**
     * Le site qui appelle doit être celui de l'entreprise (Réglages → Entreprise → Site internet).
     */
    public static function origineAutorisee(?string $origine): bool
    {
        $site = parse_url((string) reglage('identite.site'), PHP_URL_HOST);
        $hote = parse_url((string) $origine, PHP_URL_HOST);
        if (! $site || ! $hote) {
            return false;
        }
        $site = preg_replace('/^www\./', '', strtolower($site));
        $hote = preg_replace('/^www\./', '', strtolower($hote));

        return $hote === $site || str_ends_with($hote, '.'.$site);
    }

    /**
     * Empreinte du jour : sel secret du jour + adresse + navigateur, réduite à 16 caractères.
     * L'adresse IP sert seulement à ce calcul et n'est jamais enregistrée.
     */
    private function empreinte(Request $request): string
    {
        $sel = hash_hmac('sha256', today()->toDateString(), (string) config('app.key'));

        return substr(hash('sha256', $sel.'|'.$request->ip().'|'.$request->userAgent()), 0, 16);
    }

    private function chemin(string $page): string
    {
        $chemin = parse_url($page, PHP_URL_PATH);

        return is_string($chemin) && $chemin !== '' ? $chemin : '/';
    }

    private function source(string $referent): string
    {
        $hote = strtolower((string) parse_url($referent, PHP_URL_HOST));
        if ($hote === '' || self::origineAutorisee($referent)) {
            return 'Accès direct';
        }

        return match (true) {
            str_contains($hote, 'google.') => 'Google',
            str_contains($hote, 'bing.') => 'Bing',
            str_contains($hote, 'qwant.') || str_contains($hote, 'duckduckgo.') || str_contains($hote, 'ecosia.') || str_contains($hote, 'yahoo.') => 'Autres moteurs',
            str_contains($hote, 'facebook.') || str_contains($hote, 'fb.') => 'Facebook',
            str_contains($hote, 'instagram.') => 'Instagram',
            str_contains($hote, 'pagesjaunes.') => 'Pages Jaunes',
            default => Str::limit(preg_replace('/^www\./', '', $hote), 60, ''),
        };
    }

    private function appareil(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/ipad|tablet/i', $agent) => 'Tablette',
            (bool) preg_match('/mobile|iphone|android/i', $agent) => 'Téléphone',
            default => 'Ordinateur',
        };
    }
}
