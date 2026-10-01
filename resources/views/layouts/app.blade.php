<!doctype html>
<html lang="fr">
<head>
    @include('layouts.tete')
</head>
<body>
    <a class="lien-evitement" href="#contenu">Aller au contenu</a>

    <header class="entete">
        @hasSection('parent')
            <a href="@yield('parent')" class="retour" data-retour>‹ Retour</a>
        @endif
        <h1>@yield('titre')</h1>
    </header>

    <main id="contenu" class="contenu" tabindex="-1">
        @if (reglage('demo.active'))
            <p class="bandeau-demo">Démonstration : toutes les données sont fictives.</p>
        @endif

        @if (auth()->user()?->estGerant() && ! \App\Support\Configuration::estTerminee())
            <div class="message message-info bandeau-configuration">
                <strong>Configuration à terminer.</strong>
                Vos liens clients restent coupés tant qu'elle n'est pas finie.
                <a href="{{ route('configuration') }}">Continuer la configuration ›</a>
            </div>
        @endif

        @if (session('statut'))
            <div class="message message-succes" role="status">{{ session('statut') }}</div>
        @endif
        @if (session('erreur'))
            <div class="message message-erreur" role="alert">{{ session('erreur') }}</div>
        @endif

        @yield('contenu')
    </main>

    @include('layouts.navigation')
</body>
</html>
