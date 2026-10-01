@extends('layouts.app')

@section('titre', 'Guide')
@section('parent', route('plus'))

@section('contenu')
    <form method="get" action="{{ route('guide') }}" class="recherche" role="search">
        <label for="q" class="visuellement-cache">Chercher dans le guide</label>
        <input type="search" id="q" name="q" value="{{ $recherche }}" placeholder="Chercher : facture, photo, rappel…">
        <button type="submit" class="bouton">Chercher</button>
    </form>

    @if ($bienDemarrer && $recherche === '')
        @php($faits = collect($bienDemarrer)->where('fait', true)->count())
        <section class="carte" id="bien-demarrer" aria-labelledby="titre-bien-demarrer">
            <h2 id="titre-bien-demarrer">Bien démarrer ({{ $faits }}/{{ count($bienDemarrer) }})</h2>
            <ul class="liste">
                @foreach ($bienDemarrer as $point)
                    <li class="ligne">
                        <span>{{ $point['fait'] ? '✓' : '○' }} {{ $point['texte'] }}<span class="visuellement-cache"> : {{ $point['fait'] ? 'fait' : 'à faire' }}</span></span>
                        @unless ($point['fait'])
                            <a class="bouton bouton-secondaire" href="{{ $point['lien'] }}">Régler</a>
                        @endunless
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if (empty($rubriques))
        <div class="carte vide"><p>Rien trouvé pour « {{ $recherche }} ».</p><a href="{{ route('guide') }}">Voir tout le guide</a></div>
    @endif

    @foreach ($rubriques as $cle => $rubrique)
        <details class="carte rubrique-guide" id="{{ $cle }}" @if ($ouverte === $cle || $recherche !== '') open @endif>
            <summary><h2>{{ $rubrique['titre'] }}</h2></summary>
            <ol class="etapes-guide">
                @foreach ($rubrique['etapes'] as $etape)
                    <li>{{ $etape }}</li>
                @endforeach
            </ol>
            <div class="bon-a-savoir"><strong>Bon à savoir :</strong> {{ $rubrique['bon_a_savoir'] }}</div>
            @if ($rubrique['capture'] && is_file(public_path('images/guide/'.$rubrique['capture'].'.png')))
                <img class="capture-guide" src="{{ asset('images/guide/'.$rubrique['capture'].'.png') }}" alt="Capture d'écran (données fictives) : {{ $rubrique['titre'] }}" loading="lazy" width="390">
            @endif
            <a class="bouton bouton-secondaire" href="{{ route($rubrique['route']) }}">Ouvrir la page</a>
        </details>
    @endforeach
@endsection
