<?php

namespace App\Providers;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Prestation;
use App\Services\ConfigurationEmail;
use App\Support\Corbeille;
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

        // Éléments que l'on peut remettre depuis la corbeille (30 jours).
        Corbeille::enregistrer('client', Client::class, 'Client');
        Corbeille::enregistrer('chantier', Chantier::class, 'Adresse de chantier');
        Corbeille::enregistrer('devis', Devis::class, 'Devis (brouillon)');
        Corbeille::enregistrer('prestation', Prestation::class, 'Prestation du catalogue');

        // Compte Gmail des Réglages (mot de passe d'application chiffré en base).
        $this->app->booted(fn () => $this->app->make(ConfigurationEmail::class)->appliquer());
    }
}
