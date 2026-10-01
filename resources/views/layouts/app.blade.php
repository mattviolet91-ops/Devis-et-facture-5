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
        @if (session('statut'))
            <div class="message message-succes" role="status">{{ session('statut') }}</div>
        @endif

        @yield('contenu')
    </main>

    @include('layouts.navigation')
</body>
</html>
