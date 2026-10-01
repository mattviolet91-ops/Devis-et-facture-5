<?php

namespace App\Http\Middleware;

use App\Models\CleApi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * API pour Claude : clé « Bearer » d'un compte gérant actif, jamais sur l'adresse des clients.
 */
class AuthentificationApi
{
    public function handle(Request $request, Closure $next): Response
    {
        // Toujours des réponses JSON (erreurs comprises).
        $request->headers->set('Accept', 'application/json');

        if (AdresseClient::estAdresseClient($request)) {
            return response()->json(['erreur' => 'Introuvable.'], 404);
        }

        $cle = CleApi::trouver((string) $request->bearerToken());
        if (! $cle || ! $cle->user?->is_active || ! $cle->user->estGerant()) {
            return response()->json(['erreur' => 'Clé d\'accès absente, révoquée ou invalide.'], 401);
        }

        $cle->forceFill(['derniere_utilisation_at' => now()])->saveQuietly();
        Auth::setUser($cle->user);
        $request->attributes->set('cle_api', $cle);

        return $next($request);
    }
}
