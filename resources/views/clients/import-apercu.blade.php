@extends('layouts.app')

@section('titre', 'Aperçu de l\'import')
@section('parent', route('clients.import'))

@section('contenu')
    @php
        $libelles = ['civilite' => 'Civilité', 'nom' => 'Nom', 'prenom' => 'Prénom', 'raison_sociale' => 'Société', 'telephone' => 'Téléphone',
            'telephone2' => 'Autre téléphone', 'email' => 'Email', 'adresse' => 'Adresse', 'code_postal' => 'Code postal', 'ville' => 'Ville', 'provenance' => 'Provenance'];
    @endphp

    <div class="carte">
        <h2>Ce qui va se passer</h2>
        <ul>
            <li><strong>{{ $compte['ok'] ?? 0 }}</strong> client(s) à importer</li>
            <li><strong>{{ $compte['doublon'] ?? 0 }}</strong> déjà présent(s) (non importés, sauf si vous cochez la case)</li>
            <li><strong>{{ $compte['erreur'] ?? 0 }}</strong> ligne(s) incomplète(s) (ignorées)</li>
        </ul>
        <p class="aide">Colonnes reconnues : {{ implode(', ', array_map(fn ($c) => $libelles[$c] ?? $c, $colonnes)) }}.</p>
    </div>

    <form method="post" action="{{ route('clients.import.importer', $jeton) }}" class="carte">
        @csrf
        @if (($compte['doublon'] ?? 0) > 0)
            <label class="case">
                <input type="checkbox" name="avec_doublons" value="1">
                <span>Importer aussi les clients déjà présents</span>
            </label>
        @endif
        <button type="submit" class="bouton bouton-large" @disabled(($compte['ok'] ?? 0) + ($compte['doublon'] ?? 0) === 0)>Importer</button>
        <a class="bouton bouton-secondaire bouton-large" href="{{ route('clients.import') }}">Annuler</a>
    </form>

    <ul class="liste carte">
        @foreach ($lignes->take(300) as $ligne)
            <li class="ligne">
                <div>
                    <strong>{{ $ligne['donnees']['raison_sociale'] ?? trim(($ligne['donnees']['prenom'] ?? '').' '.($ligne['donnees']['nom'] ?? '')) ?: '(sans nom)' }}</strong>
                    <small>Ligne {{ $ligne['numero'] }} · {{ implode(' · ', array_filter([$ligne['donnees']['telephone'] ?? null, $ligne['donnees']['email'] ?? null, $ligne['donnees']['ville'] ?? null])) }}</small>
                    @if ($ligne['message'])
                        <small>{{ $ligne['message'] }}</small>
                    @endif
                </div>
                <span @class(['badge', 'badge-succes' => $ligne['statut'] === 'ok', 'badge-danger' => $ligne['statut'] === 'erreur'])>
                    {{ ['ok' => 'À importer', 'doublon' => 'Déjà présent', 'erreur' => 'Ignorée'][$ligne['statut']] }}
                </span>
            </li>
        @endforeach
    </ul>
    @if ($lignes->count() > 300)
        <p class="texte-doux">… et {{ $lignes->count() - 300 }} autre(s) ligne(s).</p>
    @endif
@endsection
