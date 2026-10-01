@extends('layouts.app')

@section('titre', 'Textes types')
@section('parent', route('reglages'))

@section('contenu')
    <p class="texte-doux">Des phrases que vous écrivez souvent (conditions d'accès, garanties, remarques…), prêtes à insérer.</p>

    @forelse ($textes as $index => $texte)
        <details class="carte">
            <summary><strong>{{ $texte['titre'] }}</strong></summary>
            <form method="post" action="{{ route('reglages.textes.update', $index) }}">
                @csrf
                @method('put')
                <div class="champ">
                    <label for="titre-{{ $index }}">Titre</label>
                    <input type="text" id="titre-{{ $index }}" name="titre" value="{{ $texte['titre'] }}" required>
                </div>
                <div class="champ">
                    <label for="texte-{{ $index }}">Texte</label>
                    <textarea id="texte-{{ $index }}" name="texte" rows="5" required>{{ $texte['texte'] }}</textarea>
                </div>
                <button type="submit" class="bouton">Enregistrer</button>
            </form>
            <form method="post" action="{{ route('reglages.textes.destroy', $index) }}" class="formulaire-supprimer">
                @csrf
                @method('delete')
                <button type="submit" class="bouton-lien texte-danger">Supprimer ce texte</button>
            </form>
        </details>
    @empty
        <div class="carte"><p>Aucun texte type pour le moment.</p></div>
    @endforelse

    <form method="post" action="{{ route('reglages.textes.store') }}" class="carte" novalidate>
        @csrf
        <h2>Ajouter un texte</h2>
        <x-champ nom="titre" libelle="Titre" required />
        <div class="champ">
            <label for="texte">Texte</label>
            <textarea id="texte" name="texte" rows="5" required @error('texte') aria-invalid="true" aria-describedby="texte-erreur" @enderror>{{ old('texte') }}</textarea>
            @error('texte')
                <p class="erreur-champ" id="texte-erreur">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="bouton bouton-large">Ajouter</button>
    </form>
@endsection
