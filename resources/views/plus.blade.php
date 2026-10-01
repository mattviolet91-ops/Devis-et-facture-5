@extends('layouts.app')

@section('titre', 'Plus')

@section('contenu')
    <section class="carte" aria-labelledby="titre-affichage">
        <h2 id="titre-affichage">Affichage sur ce téléphone</h2>

        <div data-si-js hidden>
            <fieldset class="segments">
                <legend>Thème</legend>
                <label><input type="radio" name="theme" value="auto"><span>Auto</span></label>
                <label><input type="radio" name="theme" value="clair"><span>Clair</span></label>
                <label><input type="radio" name="theme" value="sombre"><span>Sombre</span></label>
            </fieldset>

            <label class="case">
                <input type="checkbox" id="grands-boutons">
                <span>Grands boutons et grand texte</span>
            </label>
            <p class="aide">Ces choix sont gardés sur ce téléphone seulement.</p>
        </div>
        <p class="texte-doux" data-sans-js>Le thème suit le réglage de votre téléphone.</p>
    </section>

    <nav class="carte" aria-labelledby="titre-menu">
        <h2 id="titre-menu">Menu</h2>
        <ul class="liste">
            <li><a class="liste-lien" href="{{ route('catalogue.index') }}"><x-icone nom="journal" /><span class="libelle">Catalogue de prestations</span><x-icone nom="fleche" /></a></li>
            @if (auth()->user()->estGerant())
                <li><a class="liste-lien" href="{{ route('factures.index') }}"><x-icone nom="factures" /><span class="libelle">Factures</span><x-icone nom="fleche" /></a></li>
            @endif
            <li><a class="liste-lien" href="{{ route('bientot', 'planning') }}"><x-icone nom="planning" /><span class="libelle">Planning</span><x-icone nom="fleche" /></a></li>
            @if (auth()->user()->estGerant())
                <li><a class="liste-lien" href="{{ route('reglages') }}"><x-icone nom="reglages" /><span class="libelle">Réglages</span><x-icone nom="fleche" /></a></li>
                <li><a class="liste-lien" href="{{ route('journal') }}"><x-icone nom="journal" /><span class="libelle">Journal d'activité</span><x-icone nom="fleche" /></a></li>
                <li><a class="liste-lien" href="{{ route('corbeille') }}"><x-icone nom="corbeille" /><span class="libelle">Corbeille</span><x-icone nom="fleche" /></a></li>
            @endif
        </ul>
    </nav>

    <section class="carte" aria-labelledby="titre-compte">
        <h2 id="titre-compte">Mon compte</h2>
        <p>{{ auth()->user()->email }} <span class="badge">{{ auth()->user()->libelleRole() }}</span></p>
        <form method="post" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="bouton bouton-secondaire bouton-large"><x-icone nom="sortie" /> Se déconnecter</button>
        </form>
    </section>
@endsection
