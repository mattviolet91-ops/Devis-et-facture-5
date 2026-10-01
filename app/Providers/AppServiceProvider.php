<?php

namespace App\Providers;

use App\Models\Chantier;
use App\Models\Client;
use App\Models\Devis;
use App\Models\Facture;
use App\Models\Prestation;
use App\Models\Rapport;
use App\Models\RendezVous;
use App\Services\ConfigurationEmail;
use App\Support\Corbeille;
use App\Support\Reglages;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        if (config('app.forcer_url')) {
            URL::forceRootUrl((string) config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }

        // Éléments que l'on peut remettre depuis la corbeille (30 jours).
        Corbeille::enregistrer('client', Client::class, 'Client');
        Corbeille::enregistrer('chantier', Chantier::class, 'Adresse de chantier');
        Corbeille::enregistrer('devis', Devis::class, 'Devis (brouillon)');
        // Seuls les brouillons de facture peuvent aller à la corbeille (une facture émise est conservée).
        Corbeille::enregistrer('facture', Facture::class, 'Facture (brouillon)');
        Corbeille::enregistrer('prestation', Prestation::class, 'Prestation du catalogue');
        Corbeille::enregistrer('rapport', Rapport::class, 'Rapport d\'intervention');
        Corbeille::enregistrer('rendez_vous', RendezVous::class, 'Rendez-vous ou chantier');

        // API pour Claude : 30 appels par minute et par clé.
        RateLimiter::for('api-claude', fn (Request $request) => Limit::perMinute(30)->by('cle-'.($request->attributes->get('cle_api')?->id ?? $request->ip()))
            ->response(fn () => response()->json(['erreur' => 'Trop d\'appels : 30 par minute au plus. Réessayez dans une minute.'], 429)));

        // Compte Gmail des Réglages (mot de passe d'application chiffré en base).
        $this->app->booted(fn () => $this->app->make(ConfigurationEmail::class)->appliquer());
    }
}
