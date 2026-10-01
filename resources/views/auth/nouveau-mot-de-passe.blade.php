@extends('layouts.acces')

@section('titre', 'Nouveau mot de passe')

@section('contenu')
    <form method="post" action="{{ route('password.store') }}" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-champ nom="email" libelle="Email" type="email" :valeur="$email" autocomplete="username" required />
        <x-champ-mot-de-passe libelle="Nouveau mot de passe" autocomplete="new-password" aide="10 caractères minimum." />
        <x-champ-mot-de-passe nom="password_confirmation" id="password_confirmation" libelle="Retapez le mot de passe" autocomplete="new-password" />
        <button type="submit" class="bouton bouton-large">Enregistrer le mot de passe</button>
    </form>
@endsection
