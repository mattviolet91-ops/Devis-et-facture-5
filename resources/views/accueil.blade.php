@extends('layouts.app')

@section('titre', 'Accueil')

@section('contenu')
    @if ($alertes->isNotEmpty())
        <section class="carte" aria-labelledby="titre-alertes">
            <h2 id="titre-alertes">Alertes</h2>
            <ul class="liste">
                @foreach ($alertes as $alerte)
                    <li class="ligne">
                        <div>
                            <strong>{{ $alerte->data['titre'] ?? 'Alerte' }}</strong>
                            <small>{{ $alerte->data['texte'] ?? '' }}</small>
                        </div>
                        <form method="post" action="{{ route('alertes.lue', $alerte->id) }}">
                            @csrf
                            <button type="submit" class="bouton bouton-secondaire">Vu</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="carte" aria-labelledby="titre-aujourdhui">
        <h2 id="titre-aujourdhui">Aujourd'hui</h2>
        <p>{{ ucfirst(now()->locale('fr')->isoFormat('dddd D MMMM YYYY')) }}</p>
        <p class="texte-doux">Vos rendez-vous et chantiers du jour s'afficheront ici quand le planning sera en place.</p>
    </section>

    <section class="carte" aria-labelledby="titre-raccourcis">
        <h2 id="titre-raccourcis">Pour commencer</h2>
        <p>Touchez <strong>Nouveau</strong> en bas de l'écran pour créer un client, un devis ou un rendez-vous.</p>
        <a class="bouton bouton-large" href="{{ route('nouveau') }}">Nouveau</a>
    </section>
@endsection
