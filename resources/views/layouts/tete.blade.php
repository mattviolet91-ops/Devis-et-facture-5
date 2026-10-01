<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="{{ reglage('apparence.couleur_principale') }}">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('titre') · {{ reglage('identite.nom_commercial') ?: config('app.name') }}</title>
<link rel="manifest" href="{{ route('manifest') }}">
<link rel="icon" href="{{ asset('icons/icone.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ \App\Support\FichiersReglages::aIcone() ? route('fichiers.icone', 180) : asset('icons/icone-180.png') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<link rel="stylesheet" href="{{ route('theme') }}?v={{ substr(md5(reglage('apparence.couleur_principale').reglage('apparence.couleur_accent').reglage('apparence.police')), 0, 8) }}">
<script src="{{ asset('js/theme-init.js') }}?v={{ filemtime(public_path('js/theme-init.js')) }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
