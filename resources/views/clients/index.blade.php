@extends('layouts.app')

@section('titre', 'Clients')

@section('contenu')
    <form method="get" action="{{ route('clients.index') }}" class="recherche" role="search">
        <label for="q" class="visuellement-cache">Chercher un client</label>
        <input type="search" id="q" name="q" value="{{ $recherche }}" placeholder="Nom, ville, téléphone…" autocomplete="off" enterkeyhint="search">
        <button type="submit" class="bouton">Chercher</button>
    </form>

    <div class="actions-ligne">
        <a class="bouton" href="{{ route('clients.create') }}"><x-icone nom="plus" /> Nouveau client</a>
        <a class="bouton bouton-secondaire" href="{{ route('clients.import') }}">Importer</a>
    </div>

    @if ($total === 0)
        <div class="carte vide">
            <h2>Aucun client pour le moment</h2>
            <p>Ajoutez votre premier client, ou importez votre liste depuis un fichier Excel (CSV).</p>
            <a class="bouton bouton-large" href="{{ route('clients.create') }}">Créer mon premier client</a>
        </div>
    @elseif ($clients->isEmpty())
        <div class="carte vide">
            <p>Aucun client ne correspond à « {{ $recherche }} ».</p>
            <a href="{{ route('clients.index') }}">Voir tous les clients</a>
        </div>
    @else
        <p class="texte-doux" aria-live="polite">{{ $clients->total() }} client(s){{ $recherche !== '' ? ' trouvé(s)' : '' }}</p>
        <ul class="liste carte liste-clients">
            @foreach ($clients as $client)
                <li>
                    <a class="liste-lien" href="{{ route('clients.show', $client) }}">
                        <span class="libelle">
                            <strong>{{ $client->nomComplet() }}</strong>
                            @if ($client->estProfessionnel())
                                <span class="badge">Pro</span>
                            @endif
                            <small class="texte-doux bloc">{{ trim(($client->ville ?? '').($client->telephone ? ' · '.\App\Support\Telephone::formater($client->telephone) : ''), ' ·') }}</small>
                        </span>
                        <x-icone nom="fleche" />
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($clients->hasPages())
            <nav class="pagination" aria-label="Pages de la liste">
                @if ($clients->previousPageUrl())
                    <a class="bouton bouton-secondaire" href="{{ $clients->previousPageUrl() }}">‹ Précédents</a>
                @else
                    <span></span>
                @endif
                @if ($clients->nextPageUrl())
                    <a class="bouton bouton-secondaire" href="{{ $clients->nextPageUrl() }}">Suivants ›</a>
                @endif
            </nav>
        @endif
    @endif
@endsection
