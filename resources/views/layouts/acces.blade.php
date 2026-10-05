<!doctype html>
<html lang="fr">
<head>
    @include('layouts.tete')
</head>
<body class="page-acces" data-sans-historique>
    <main id="contenu" class="contenu">
        <div class="marque">
            @if (reglage('apparence.logo'))
                <img class="marque-logo" src="{{ route('fichiers.logo') }}" alt="{{ reglage('identite.nom_commercial') }}">
            @else
                <img class="logo-app" src="{{ asset('icons/icone.svg') }}" alt="" width="64" height="64">
                @if (reglage('identite.nom_commercial'))
                    <p class="marque-nom">{{ reglage('identite.nom_commercial') }}</p>
                @endif
            @endif
            @if (reglage('identite.slogan'))
                <p class="marque-slogan">{{ reglage('identite.slogan') }}</p>
            @endif
        </div>

        @if (reglage('demo.active'))
            <p class="bandeau-demo">Démonstration : toutes les données sont fictives.</p>
        @endif

        @if (session('statut'))
            <div class="message message-succes" role="status">{{ session('statut') }}</div>
        @endif

        <div class="carte">
            <h1>@yield('titre')</h1>
            @yield('contenu')
        </div>
    </main>
</body>
</html>
