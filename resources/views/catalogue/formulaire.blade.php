@extends('layouts.app')

@section('titre', $prestation->exists ? 'Modifier la prestation' : 'Nouvelle prestation')
@section('parent', route('catalogue.index'))

@section('contenu')
    @if ($errors->any())
        <div class="message message-erreur" role="alert">Certains champs sont à corriger ({{ $errors->count() }}). Ils sont signalés en rouge.</div>
    @endif

    <form method="post" action="{{ $prestation->exists ? route('catalogue.update', $prestation) : route('catalogue.store') }}" class="carte" novalidate>
        @csrf
        @if ($prestation->exists)
            @method('put')
        @endif

        <x-champ nom="nom" libelle="Nom de la prestation" :valeur="$prestation->nom" />
        <x-champ nom="categorie" libelle="Catégorie" :valeur="$prestation->categorie" list="categories" aide="Par exemple : Entretien, Couverture, Zinguerie." />
        <datalist id="categories">
            @foreach (\App\Models\Prestation::query()->whereNotNull('categorie')->distinct()->orderBy('categorie')->pluck('categorie') as $categorie)
                <option value="{{ $categorie }}">
            @endforeach
        </datalist>
        <x-champ-texte-long nom="description" libelle="Description (facultatif)" :valeur="$prestation->description" :lignes="3" aide="Elle apparaît sous la ligne dans le devis." />

        <div class="champ">
            <label for="prix">Prix unitaire HT (€)</label>
            <input type="text" id="prix" name="prix" inputmode="decimal" value="{{ old('prix', $prestation->prix_ht !== null ? number_format($prestation->prix_ht / 100, 2, ',', '') : '') }}"
                aria-describedby="prix-aide @error('prix_lisible') prix-erreur @enderror" @error('prix_lisible') aria-invalid="true" @enderror>
            <p class="aide" id="prix-aide">Laissez vide si le prix change à chaque fois.</p>
            @error('prix_lisible')
                <p class="erreur-champ" id="prix-erreur">{{ $message }}</p>
            @enderror
        </div>

        <x-champ-liste nom="unite" libelle="Unité" :options="(array) reglage('tva.unites')" :valeur="$prestation->unite" :vide="false" />

        @unless (\App\Support\Tva::estFranchise())
            <x-champ-liste nom="taux_tva" libelle="TVA" :options="\App\Support\Tva::options()" :valeur="$prestation->taux_tva" vide="Taux par défaut ({{ \App\Support\Tva::formater((int) reglage('tva.taux_defaut')) }})" />
        @endunless

        <button type="submit" class="bouton bouton-large">Enregistrer</button>
    </form>

    @if ($prestation->exists)
        <form method="post" action="{{ route('catalogue.destroy', $prestation) }}" class="zone-suppression">
            @csrf
            @method('delete')
            <button type="submit" class="bouton bouton-secondaire bouton-large texte-danger" data-confirmer="Mettre cette prestation à la corbeille ?">Mettre à la corbeille</button>
        </form>
    @endif
@endsection
