@extends('layouts.configuration')

@section('titre', 'Configuration en cours')

@section('contenu')
    <div class="carte">
        <h2>L'application est en cours de configuration par le gérant.</h2>
        <p>Vous pourrez l'utiliser dès qu'il aura terminé. Revenez un peu plus tard.</p>
        <a class="bouton bouton-large" href="{{ route('accueil') }}">Réessayer</a>
    </div>
@endsection
