@extends('layouts.app')

@section('titre', $chantier->exists ? 'Modifier le chantier' : 'Nouvelle adresse de chantier')
@section('parent', route('clients.show', $client))

@section('contenu')
    <p class="texte-doux">Client : <strong>{{ $client->nomComplet() }}</strong></p>

    @if ($errors->any())
        <div class="message message-erreur" role="alert">Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge.</div>
    @endif

    <form method="post" action="{{ $chantier->exists ? route('chantiers.update', $chantier) : route('chantiers.store', $client) }}" class="carte" novalidate>
        @csrf
        @if ($chantier->exists)
            @method('put')
        @endif

        <x-champ nom="libelle" libelle="Nom du chantier (facultatif)" :valeur="$chantier->libelle" aide="Par exemple : Maison principale, Grange, Résidence secondaire." />
        <x-champ nom="adresse" libelle="Adresse" :valeur="$chantier->adresse" autocomplete="street-address" />
        <x-champ nom="code_postal" libelle="Code postal" :valeur="$chantier->code_postal" inputmode="numeric" />
        <x-champ nom="ville" libelle="Ville" :valeur="$chantier->ville" />

        @if (\App\Support\Metiers::moduleActif('calculateur_toiture') || reglage('entreprise.metier') === 'couvreur')
            <h2>Toiture</h2>
            <x-champ-liste nom="type_toiture" libelle="Type de toiture" :options="\App\Models\Chantier::TYPES_TOITURE" :valeur="$chantier->type_toiture" />
            <x-champ nom="surface" libelle="Surface (m²)" :valeur="$chantier->surface !== null ? str_replace('.', ',', rtrim(rtrim((string) $chantier->surface, '0'), '.')) : null" inputmode="decimal" />
            <x-champ nom="pente" libelle="Pente (en degrés)" type="number" :valeur="$chantier->pente" inputmode="numeric" min="0" max="90" aide="Entre 0° (plat) et 90°. Une pente de 100 % vaut 45°." />
        @endif

        <x-champ-texte-long nom="acces" libelle="Accès" :valeur="$chantier->acces" :lignes="2" aide="Échelle, échafaudage, nacelle, stationnement, clés, chien…" />
        <x-champ-texte-long nom="notes" libelle="Remarques" :valeur="$chantier->notes" />

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>

    @if ($chantier->exists)
        <form method="post" action="{{ route('chantiers.destroy', $chantier) }}" class="zone-suppression">
            @csrf
            @method('delete')
            <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Mettre cette adresse de chantier à la corbeille ?">Mettre à la corbeille</button>
        </form>
    @endif
@endsection
