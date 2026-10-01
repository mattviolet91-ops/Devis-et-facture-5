@extends('layouts.acces')

@section('titre', 'Connexion')

@section('contenu')
    <form method="post" action="{{ url('/connexion') }}" novalidate>
        @csrf
        <x-champ nom="email" libelle="Email" type="email" autocomplete="username" inputmode="email" required autofocus />
        <x-champ-mot-de-passe />

        <label class="case">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>Rester connecté sur ce téléphone</span>
        </label>

        <button type="submit" class="bouton bouton-large">Se connecter</button>
    </form>

    <p class="liens-bas"><a href="{{ route('password.request') }}">Mot de passe oublié ?</a></p>
@endsection
