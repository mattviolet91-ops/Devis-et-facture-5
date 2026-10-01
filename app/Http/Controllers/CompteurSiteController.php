<?php

namespace App\Http\Controllers;

use App\Services\CompteurSite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Petit compteur à coller sur le site de l'entreprise : une seule balise <script>.
 */
class CompteurSiteController extends Controller
{
    public function script(): Response
    {
        $actif = (bool) reglage('site.compteur_actif');
        $adresse = json_encode(route('compteur.collecter'), JSON_UNESCAPED_SLASHES);

        $script = $actif ? <<<JS
/* Compteur de visites : aucun cookie, aucune donnée personnelle. */
(function () {
    'use strict';
    var u = {$adresse};
    function envoyer(e) {
        try {
            var d = JSON.stringify({ e: e, p: location.pathname, r: e === 'vue' ? document.referrer : '' });
            if (navigator.sendBeacon) { navigator.sendBeacon(u, new Blob([d], { type: 'text/plain' })); }
            else { fetch(u, { method: 'POST', body: d, keepalive: true, mode: 'no-cors' }); }
        } catch (x) { /* rien */ }
    }
    envoyer('vue');
    document.addEventListener('click', function (ev) {
        var a = ev.target && ev.target.closest ? ev.target.closest('a') : null;
        if (!a) { return; }
        var h = (a.getAttribute('href') || '').toLowerCase();
        if (h.indexOf('tel:') === 0) { envoyer('appeler'); }
        else if (h.indexOf('mailto:') === 0) { envoyer('email'); }
        else if (/wa\\.me|whatsapp/.test(h)) { envoyer('whatsapp'); }
        else if (/devis|\\/demande/.test(h) || /devis/i.test(a.textContent || '')) { envoyer('devis'); }
    }, true);
    document.addEventListener('submit', function () { envoyer('formulaire'); }, true);
})();
JS : '/* Compteur désactivé. */';

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }

    public function collecter(Request $request, CompteurSite $compteur): Response
    {
        if (reglage('site.compteur_actif') && CompteurSite::origineAutorisee($request->headers->get('Origin') ?: $request->headers->get('Referer'))) {
            $compteur->enregistrer($request);
        }

        return response('', 204);
    }
}
