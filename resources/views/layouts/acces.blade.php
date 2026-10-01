<!doctype html>
<html lang="fr">
<head>
    @include('layouts.tete')
</head>
<body class="page-acces" data-sans-historique>
    <main id="contenu" class="contenu">
        <img class="logo-app" src="{{ asset('icons/icone.svg') }}" alt="" width="72" height="72">
        <h1>@yield('titre')</h1>

        @if (session('statut'))
            <div class="message message-succes" role="status">{{ session('statut') }}</div>
        @endif

        <div class="carte">
            @yield('contenu')
        </div>
    </main>
</body>
</html>
