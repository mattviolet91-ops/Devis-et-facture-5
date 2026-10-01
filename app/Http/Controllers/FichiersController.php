<?php

namespace App\Http\Controllers;

use App\Support\Couleurs;
use App\Support\FichiersReglages;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FichiersController extends Controller
{
    /**
     * Couleurs et police choisies dans Réglages → Apparence (aucun style inline).
     */
    public function theme(): Response
    {
        $principale = (string) reglage('apparence.couleur_principale');
        $accent = (string) reglage('apparence.couleur_accent');

        $principaleClaire = Couleurs::pourModeClair($principale, '#f4f5f7');
        $principaleSombre = Couleurs::pourModeSombre($principale);
        $accentSombre = Couleurs::pourModeSombre($accent);

        $polices = [
            'systeme' => 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
            'arrondie' => 'ui-rounded, "SF Pro Rounded", "Nunito", "Varela Round", system-ui, sans-serif',
            'classique' => 'Georgia, Cambria, "Times New Roman", serif',
        ];
        $police = $polices[reglage('apparence.police')] ?? $polices['systeme'];

        $sombre = "--couleur-principale: {$principaleSombre}; --couleur-principale-texte: ".Couleurs::texteSur($principaleSombre).
            "; --couleur-accent: {$accentSombre}; --couleur-accent-texte: ".Couleurs::texteSur($accentSombre).';';

        $css = ":root { --police: {$police}; --couleur-principale: {$principaleClaire}; --couleur-principale-texte: ".Couleurs::texteSur($principaleClaire).
            "; --couleur-accent: {$accent}; --couleur-accent-texte: ".Couleurs::texteSur($accent)."; }\n".
            "@media (prefers-color-scheme: dark) { :root:not([data-theme=\"clair\"]) { {$sombre} } }\n".
            ":root[data-theme=\"sombre\"] { {$sombre} }\n";

        return response($css, 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function logo(): StreamedResponse
    {
        return $this->servir((string) reglage('apparence.logo'));
    }

    public function icone(int $taille): StreamedResponse
    {
        abort_unless(in_array($taille, FichiersReglages::TAILLES_ICONE, true) && FichiersReglages::aIcone(), 404);

        return $this->servir(FichiersReglages::cheminIcone($taille));
    }

    public function attestation(): StreamedResponse
    {
        return $this->servir((string) reglage('assurance.attestation'), 'attestation-assurance.pdf');
    }

    private function servir(string $chemin, ?string $nom = null): StreamedResponse
    {
        $disque = FichiersReglages::disque();
        abort_if($chemin === '' || ! $disque->exists($chemin), 404);

        return $disque->response($chemin, $nom, [
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], $nom ? 'inline' : 'inline');
    }
}
