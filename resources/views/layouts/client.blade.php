<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('titre') · {{ reglage('identite.nom_commercial') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ route('theme') }}">
    <script src="{{ asset('js/theme-init.js') }}"></script>
    @stack('scripts')
</head>
<body class="page-client">
    <header class="entete entete-client">
        @if (reglage('apparence.logo'))
            <img src="{{ route('fichiers.logo') }}" alt="" class="logo-client">
        @endif
        <p class="nom-entreprise">{{ reglage('identite.nom_commercial') }}</p>
    </header>
    <main id="contenu" class="contenu">
        @if (reglage('demo.active'))
            <p class="bandeau-demo">Démonstration : toutes les données sont fictives.</p>
        @endif
        @if (session('statut'))
            <div class="message message-succes" role="status">{{ session('statut') }}</div>
        @endif
        @yield('contenu')
    </main>
    <footer class="pied-client">
        <p>{{ reglage('identite.nom_commercial') }} · {{ reglage('identite.adresse') }}, {{ reglage('identite.code_postal') }} {{ reglage('identite.ville') }}</p>
        <p>Tél. <a href="tel:{{ preg_replace('/\D/', '', (string) reglage('identite.telephone')) }}">{{ reglage('identite.telephone') }}</a> · <a href="mailto:{{ reglage('identite.email') }}">{{ reglage('identite.email') }}</a></p>
    </footer>
</body>
</html>
