@extends('layouts.acces')

@section('titre', 'Mot de passe oublié')

@section('contenu')
    <p>Indiquez votre email : vous recevrez un lien pour choisir un nouveau mot de passe.</p>

    <form method="post" action="{{ route('password.email') }}" novalidate>
        @csrf
        <x-champ nom="email" libelle="Email" type="email" autocomplete="username" inputmode="email" required autofocus />
        <button type="submit" class="bouton bouton-large">Recevoir le lien</button>
    </form>

    <p class="liens-bas"><a href="{{ route('login') }}">‹ Retour à la connexion</a></p>
@endsection
