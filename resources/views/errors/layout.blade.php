<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titre')</title>
    <link rel="stylesheet" href="/css/app.css">
    <script src="/js/theme-init.js"></script>
</head>
<body class="page-erreur">
    <main id="contenu" class="contenu">
        <div class="carte">
            <p class="code">@yield('code')</p>
            <h1>@yield('titre')</h1>
            <p>@yield('message')</p>
            @unless (View::hasSection('sans-bouton'))
                <a class="bouton bouton-large" href="/accueil">Revenir à l'accueil</a>
            @endunless
        </div>
    </main>
</body>
</html>
