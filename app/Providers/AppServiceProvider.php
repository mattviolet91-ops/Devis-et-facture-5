<?php

namespace App\Providers;

use App\Services\ConfigurationEmail;
use App\Support\Reglages;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Reglages::class);
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Compte Gmail des Réglages (mot de passe d'application chiffré en base).
        $this->app->booted(fn () => $this->app->make(ConfigurationEmail::class)->appliquer());
    }
}
