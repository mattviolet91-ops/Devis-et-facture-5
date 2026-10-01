<?php

namespace App\Http\Middleware;

use App\Support\Configuration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant que le menu de configuration n'est ni terminé ni passé par le gérant,
 * toutes les pages renvoient vers lui (sauf la déconnexion).
 * Un commercial voit « en cours de configuration par le gérant ».
 */
class ConfigurationRequise
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! Configuration::bloquee() || $request->routeIs('configuration*', 'logout', 'reglages.attestation')) {
            return $next($request);
        }

        if (! $request->user()->estGerant()) {
            return response()->view('configuration.attente');
        }

        return redirect()->route('configuration.etape', Configuration::etapeAReprendre());
    }
}
