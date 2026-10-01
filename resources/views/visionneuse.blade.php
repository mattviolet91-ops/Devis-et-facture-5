<!doctype html>
<html lang="fr">
<head>
    @include('layouts.tete')
    <script type="module" src="{{ asset('js/visionneuse.js') }}?v={{ filemtime(public_path('js/visionneuse.js')) }}"></script>
</head>
<body class="page-visionneuse">
    <header class="barre-visionneuse">
        <a href="{{ $retour }}" class="bouton bouton-secondaire" data-retour>✕ Fermer</a>
        <h1 class="visuellement-cache">{{ $titre }}</h1>
        <button type="button" class="bouton" id="partager" data-titre="{{ $titre }}">Partager</button>
    </header>
    <main id="contenu" class="pages-pdf" data-document="{{ $document }}" data-worker="{{ asset('vendor/pdfjs/pdf.worker.min.mjs') }}" data-pdfjs="{{ asset('vendor/pdfjs/pdf.min.mjs') }}">
        <p class="chargement" id="etat" role="status">Chargement du document…</p>
    </main>
    <noscript><p class="contenu"><a href="{{ $document }}">Ouvrir le document</a></p></noscript>
</body>
</html>
