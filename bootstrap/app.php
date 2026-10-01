<?php

use App\Http\Middleware\CompteActif;
use App\Http\Middleware\EnTetesSecurite;
use App\Http\Middleware\EstGerant;
use App\Http\Middleware\ForcerHttps;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ForcerHttps::class);
        $middleware->append(EnTetesSecurite::class);
        $middleware->web(append: [CompteActif::class]);
        $middleware->alias(['gerant' => EstGerant::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('accueil'));
        $middleware->encryptCookies(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReportDuplicates();
    })->create();
