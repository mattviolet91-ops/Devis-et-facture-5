@extends('layouts.app')

@section('titre', $client->exists ? 'Modifier le client' : 'Nouveau client')
@section('parent', $client->exists ? route('clients.show', $client) : route('clients.index'))

@section('contenu')
    @if (session('doublons'))
        <div class="message message-erreur" role="alert">
            <strong>Attention, ce client existe peut-être déjà</strong> (même téléphone ou même email) :
            <ul>
                @foreach (session('doublons') as $doublon)
                    <li><a href="{{ route('clients.show', $doublon['id']) }}">{{ $doublon['nom'] }}</a>{{ $doublon['ville'] ? ' — '.$doublon['ville'] : '' }}</li>
                @endforeach
            </ul>
            Si c'est bien une autre personne, cochez la case en bas puis enregistrez.
        </div>
    @endif

    @if ($errors->any())
        <div class="message message-erreur" role="alert">Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge.</div>
    @endif

    <form method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="carte" novalidate>
        @csrf
        @if ($client->exists)
            @method('put')
        @endif

        <fieldset class="segments segments-2">
            <legend>Type de client</legend>
            @foreach (['particulier' => 'Particulier', 'professionnel' => 'Professionnel'] as $valeur => $texte)
                <label><input type="radio" name="type" value="{{ $valeur }}" data-type-client @checked(old('type', $client->type) === $valeur)><span>{{ $texte }}</span></label>
            @endforeach
        </fieldset>

        <div data-pour-type="professionnel">
            <x-champ nom="raison_sociale" libelle="Nom de la société" :valeur="$client->raison_sociale" autocomplete="organization" />
            <x-champ nom="siret" libelle="SIRET (facultatif)" :valeur="$client->siret" inputmode="numeric" />
            <x-champ nom="tva_intracom" libelle="N° de TVA intracommunautaire (facultatif)" :valeur="$client->tva_intracom" />
            <p class="etiquette">Personne à contacter</p>
        </div>

        <x-champ-liste nom="civilite" libelle="Civilité" :options="\App\Models\Client::CIVILITES" :valeur="$client->civilite" />
        <x-champ nom="nom" libelle="Nom" :valeur="$client->nom" autocomplete="family-name" autocapitalize="words" />
        <x-champ nom="prenom" libelle="Prénom" :valeur="$client->prenom" autocomplete="given-name" autocapitalize="words" />

        <x-champ nom="telephone" libelle="Téléphone" type="tel" :valeur="\App\Support\Telephone::formater($client->telephone)" autocomplete="tel" aide="Un téléphone ou un email est nécessaire." />
        <x-champ nom="telephone2" libelle="Autre téléphone" type="tel" :valeur="\App\Support\Telephone::formater($client->telephone2)" />
        <x-champ nom="email" libelle="Email" type="email" :valeur="$client->email" autocomplete="email" inputmode="email" />

        <x-champ nom="adresse" libelle="Adresse" :valeur="$client->adresse" autocomplete="street-address" />
        <x-champ nom="code_postal" libelle="Code postal" :valeur="$client->code_postal" inputmode="numeric" autocomplete="postal-code" />
        <x-champ nom="ville" libelle="Ville" :valeur="$client->ville" autocomplete="address-level2" />

        <x-champ-liste nom="provenance" libelle="Comment nous a-t-il connus ?" :options="(array) reglage('clients.provenances')" :valeur="$client->provenance" />
        <x-champ nom="provenance_detail" libelle="Précision (facultatif)" :valeur="$client->provenance_detail" aide="Par exemple : recommandé par M. Durand." />

        @if (session('doublons'))
            <label class="case">
                <input type="checkbox" name="confirmer_doublon" value="1">
                <span>Ce n'est pas le même client : enregistrer quand même</span>
            </label>
        @endif

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>
@endsection
