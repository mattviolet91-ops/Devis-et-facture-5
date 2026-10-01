<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * L'adresse réservée aux clients (ex. devis.mondomaine.fr) n'ouvre que les pages clients.
 */
class AdresseClient
{
    public static function estAdresseClient(Request $request): bool
    {
        $hote = parse_url((string) config('app.client_url'), PHP_URL_HOST);

        return $hote && strcasecmp($hote, $request->getHost()) === 0
            && strcasecmp($hote, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) !== 0;
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Appliqué avant tout le reste (y compris la connexion) : seules les adresses clients passent.
        if (self::estAdresseClient($request) && ! preg_match('#^(c/[A-Za-z0-9]+(/.*)?|theme\.css|fichiers/logo|paiement/mypos/notification|css/.+|js/.+|icons/.+|vendor/.+|robots\.txt|up)$#', $request->path())) {
            abort(404);
        }

        return $next($request);
    }
}
