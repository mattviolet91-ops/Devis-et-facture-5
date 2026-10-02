@extends('layouts.app')

@section('titre', $rdv->exists ? 'Modifier' : ($rdv->devis_id ? 'Planifier le chantier' : 'Nouveau rendez-vous'))
@section('parent', $rdv->exists ? route('planning.show', $rdv) : route('planning.index'))

@push('scripts')
    <script src="{{ asset('js/planning.js') }}?v={{ filemtime(public_path('js/planning.js')) }}" defer></script>
@endpush

@section('contenu')
    @if ($errors->any())
        <div class="message message-erreur" role="alert">Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge.</div>
    @endif

    <form method="post" action="{{ $rdv->exists ? route('planning.update', $rdv) : route('planning.store') }}" class="carte" novalidate>
        @csrf
        @if ($rdv->exists)
            @method('put')
        @endif
        <input type="hidden" name="devis_id" value="{{ old('devis_id', $rdv->devis_id) }}">

        <fieldset class="segments">
            <legend>Type</legend>
            <label><input type="radio" name="type" value="rdv" @checked(old('type', $rdv->type) === 'rdv')><span>Rendez-vous</span></label>
            <label><input type="radio" name="type" value="chantier" @checked(old('type', $rdv->type) === 'chantier')><span>Chantier</span></label>
        </fieldset>

        <x-champ nom="titre" libelle="Objet" :valeur="$rdv->titre" aide="Par exemple : Visite pour devis, Réfection de toiture." />

        <label class="case">
            <input type="hidden" name="journee_entiere" value="0">
            <input type="checkbox" name="journee_entiere" value="1" @checked(old('journee_entiere', $rdv->journee_entiere))>
            <span>Toute la journée (sans heure)</span>
        </label>

        <div class="grille-2">
            <x-champ nom="date_debut" libelle="Date" type="date" :valeur="$rdv->debut?->toDateString()" />
            <x-champ nom="heure_debut" libelle="Heure de début" type="time" :valeur="$rdv->journee_entiere ? null : $rdv->debut?->format('H:i')" />
        </div>
        <div class="grille-2">
            <x-champ nom="date_fin" libelle="Date de fin" type="date" :valeur="$rdv->fin?->toDateString()" aide="Pour un chantier sur plusieurs jours." />
            <x-champ nom="heure_fin" libelle="Heure de fin" type="time" :valeur="$rdv->journee_entiere ? null : $rdv->fin?->format('H:i')" />
        </div>

        <x-champ-liste nom="client_id" libelle="Client" :options="$clients" :valeur="$rdv->client_id" vide="— Aucun —" />
        <x-champ-liste nom="chantier_id" libelle="Adresse de chantier" :options="$chantiers" :valeur="$rdv->chantier_id" vide="— Adresse du client —" />
        <x-champ nom="lieu" libelle="Autre lieu (facultatif)" :valeur="$rdv->lieu" aide="Seulement si ce n'est ni l'adresse du client ni celle du chantier." autocomplete="street-address" />
        <x-champ-liste nom="user_id" libelle="Qui y va ?" :options="$personnes" :valeur="$rdv->user_id" vide="— Personne en particulier —" />
        <x-champ-liste nom="rappel_client_jours" libelle="Email de rappel au client" :options="['1' => 'La veille (à 9 h)', '2' => '2 jours avant (à 9 h)']" :valeur="$rdv->rappel_client_jours" vide="Non" aide="Envoyé seulement si le client a une adresse email." />
        <x-champ-texte-long nom="notes" libelle="Remarques" :valeur="$rdv->notes" />

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>

    @if ($rdv->exists)
        <form method="post" action="{{ route('planning.destroy', $rdv) }}" class="zone-suppression">
            @csrf
            @method('delete')
            <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Retirer du planning ?">Mettre à la corbeille</button>
        </form>
    @endif
@endsection
