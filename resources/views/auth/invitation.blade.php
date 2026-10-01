@extends('layouts.acces')

@section('titre', 'Invitation')

@section('contenu')
    @if (! $valide)
        <div class="message message-erreur" role="alert">Ce lien d'invitation n'est plus valable. Demandez au gérant de vous renvoyer une invitation.</div>
        <a class="bouton bouton-large" href="{{ route('login') }}">Aller à la connexion</a>
    @else
        <p>Bienvenue ! Choisissez votre mot de passe pour <strong>{{ $email }}</strong>.</p>
        <form method="post" action="{{ route('invitation.store', $jeton) }}" novalidate>
            @csrf
            <input type="email" name="email" value="{{ $email }}" autocomplete="username" hidden>
            <x-champ-mot-de-passe libelle="Mot de passe" autocomplete="new-password" aide="10 caractères minimum." />
            <x-champ-mot-de-passe nom="password_confirmation" id="password_confirmation" libelle="Retapez le mot de passe" autocomplete="new-password" />
            <button type="submit" class="bouton bouton-large">Créer mon compte</button>
        </form>
    @endif
@endsection
