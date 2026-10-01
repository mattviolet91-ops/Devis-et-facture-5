<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité, dont une Content-Security-Policy stricte :
 * aucun script ni style inline, tout vient de l'application elle-même.
 */
class EnTetesSecurite
{
    public const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; "
        ."font-src 'self'; connect-src 'self'; media-src 'self' blob:; worker-src 'self'; manifest-src 'self'; "
        ."frame-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $entetes = $response->headers;
        $csp = self::CSP;

        // Une page précise peut envoyer un formulaire vers un prestataire (paiement myPOS).
        $extra = (string) $entetes->get('X-Form-Action-Extra');
        if (preg_match('#^https://[a-z0-9.-]+$#', $extra)) {
            $csp = str_replace("form-action 'self'", "form-action 'self' ".$extra, $csp);
        }
        $entetes->remove('X-Form-Action-Extra');
        $entetes->set('Content-Security-Policy', $csp);
        $entetes->set('X-Content-Type-Options', 'nosniff');
        $entetes->set('X-Frame-Options', 'DENY');
        $entetes->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $entetes->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(self), payment=(), usb=(), interest-cohort=()');
        $entetes->set('Cross-Origin-Opener-Policy', 'same-origin');
        $entetes->remove('X-Powered-By');

        if ($request->isSecure()) {
            $entetes->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
