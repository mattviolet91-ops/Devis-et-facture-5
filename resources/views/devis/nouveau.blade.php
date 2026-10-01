@extends('layouts.app')

@section('titre', 'Nouveau devis')
@section('parent', route('devis.index'))

@section('contenu')
    <p><a href="{{ route('devis.express') }}">Plus rapide : écrire ou dicter le devis en une phrase (devis express) ›</a></p>

    @if (! $client)
        <div class="carte">
            <h2>Pour quel client ?</h2>
            <form method="get" action="{{ route('devis.create') }}" class="recherche" role="search">
                <label for="q" class="visuellement-cache">Chercher un client</label>
                <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Nom, ville…" autocomplete="off">
                <button type="submit" class="bouton">Chercher</button>
            </form>
            <ul class="liste">
                @forelse ($clients as $c)
                    <li><a class="liste-lien" href="{{ route('devis.create', ['client' => $c->id]) }}"><span class="libelle">{{ $c->nomComplet() }}<small class="bloc texte-doux">{{ $c->ville }}</small></span><x-icone nom="fleche" /></a></li>
                @empty
                    <li class="texte-doux">Aucun client trouvé.</li>
                @endforelse
            </ul>
            <a class="bouton bouton-secondaire bouton-large" href="{{ route('clients.create') }}">Nouveau client</a>
        </div>
    @else
        <form method="post" action="{{ route('devis.store') }}" class="carte" novalidate>
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <p>Client : <strong>{{ $client->nomComplet() }}</strong> · <a href="{{ route('devis.create') }}">changer</a></p>
            @if ($client->chantiers->isNotEmpty())
                <x-champ-liste nom="chantier_id" libelle="Adresse des travaux" :options="$client->chantiers->mapWithKeys(fn ($c) => [$c->id => $c->titre()])->all()" vide="Adresse du client" />
            @endif
            <x-champ nom="objet" libelle="Objet (facultatif)" aide="Par exemple : Démoussage et traitement de la toiture." />
            <button type="submit" class="bouton bouton-large">Créer le devis</button>
        </form>
    @endif
@endsection
