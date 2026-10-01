@extends('layouts.app')

@section('titre', $titre)
@section('parent', route('accueil'))

@section('contenu')
    <div class="carte">
        <h2>Bientôt disponible</h2>
        <p>La partie « {{ $titre }} » sera ajoutée dans une prochaine étape.</p>
    </div>
@endsection
