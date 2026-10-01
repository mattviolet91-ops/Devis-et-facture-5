<!doctype html>
<html lang="fr">
<head>
    @include('layouts.tete')
</head>
<body class="page-configuration">
    <a class="lien-evitement" href="#contenu">Aller au contenu</a>

    <header class="entete">
        @hasSection('parent')
            <a href="@yield('parent')" class="retour" data-retour>‹ Retour</a>
        @endif
        <h1>@yield('titre')</h1>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="bouton-entete">Quitter</button>
        </form>
    </header>

    <main id="contenu" class="contenu" tabindex="-1">
        @if (reglage('demo.active'))
            <p class="bandeau-demo">Démonstration : toutes les données sont fictives.</p>
        @endif

        @if (session('statut'))
            <div class="message message-succes" role="status">{{ session('statut') }}</div>
        @endif

        @yield('contenu')
    </main>
</body>
</html>
