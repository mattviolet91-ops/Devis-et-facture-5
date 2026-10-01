<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EstGerant
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->estGerant(), 403, 'Cette page est réservée au gérant.');

        return $next($request);
    }
}
