@extends('layouts.app')

@section('titre', $rapport->exists ? 'Modifier le rapport' : 'Rapport d\'intervention')
@section('parent', $rapport->exists ? route('rapports.show', $rapport) : route('clients.show', $client))

@section('contenu')
    <p class="texte-doux">Client : <strong>{{ $client->nomComplet() }}</strong></p>

    @if ($errors->any())
        <div class="message message-erreur" role="alert">Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge.</div>
    @endif

    <form method="post" action="{{ $rapport->exists ? route('rapports.update', $rapport) : route('rapports.store') }}" class="carte" novalidate>
        @csrf
        @if ($rapport->exists)
            @method('put')
        @else
            <input type="hidden" name="client_id" value="{{ $client->id }}">
        @endif
        <input type="hidden" name="rendez_vous_id" value="{{ $rapport->rendez_vous_id }}">

        <x-champ nom="titre" libelle="Objet" :valeur="$rapport->titre" aide="Par exemple : Recherche de fuite et réparation." />
        <x-champ nom="date_intervention" libelle="Date de l'intervention" type="date" :valeur="$rapport->date_intervention?->toDateString()" />
        @if ($client->chantiers->isNotEmpty())
            <x-champ-liste nom="chantier_id" libelle="Adresse de chantier" :options="$client->chantiers->mapWithKeys(fn ($c) => [$c->id => $c->titre()])" :valeur="$rapport->chantier_id" vide="— Adresse du client —" />
        @endif
        <x-champ-texte-long nom="travaux" libelle="Travaux réalisés" :valeur="$rapport->travaux" :lignes="4" />
        <x-champ-texte-long nom="constats" libelle="Constat" :valeur="$rapport->constats" aide="Ce que vous avez vu : état des tuiles, des solins, de la charpente…" />
        <x-champ-texte-long nom="conseils" libelle="Préconisations" aide="Travaux conseillés, entretien à prévoir…" :valeur="$rapport->conseils" />

        @php($choisies = array_map('intval', (array) old('photos', $rapport->photos)))
        <fieldset class="champ">
            <legend>Photos à mettre dans le rapport</legend>
            @if ($client->photos->isEmpty())
                <p class="texte-doux">Aucune photo pour ce client. <a href="{{ route('photos.index', $client) }}">Ajouter des photos</a></p>
            @else
                <ul class="choix-photos">
                    @foreach ($client->photos as $photo)
                        <li>
                            <label>
                                <input type="checkbox" name="photos[]" value="{{ $photo->id }}" @checked(in_array($photo->id, $choisies, true))>
                                <img src="{{ route('photos.miniature', $photo) }}" alt="{{ $photo->texteAlternatif() }}" loading="lazy">
                                <small>{{ $photo->libelleMoment() }}</small>
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
        </fieldset>

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>

    @if ($rapport->exists)
        <form method="post" action="{{ route('rapports.destroy', $rapport) }}" class="zone-suppression">
            @csrf
            @method('delete')
            <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Mettre ce rapport à la corbeille ?">Mettre à la corbeille</button>
        </form>
    @endif
@endsection
